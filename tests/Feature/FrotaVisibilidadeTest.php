<?php

namespace Tests\Feature;

use App\Filament\Pages\FrotaDisponibilidade;
use App\Filament\Pages\PendenciasFrota;
use App\Models\Asset;
use App\Models\FleetDriver;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Frota\KitSegurancaService;
use App\Services\Frota\MultaService;
use App\Services\Frota\PendenciasFrotaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

/** As telas e as pendências da frota só aparecem para quem tem o módulo liberado (plano + permissão). */
class FrotaVisibilidadeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        PendenciasFrotaService::esquecerPermissoes();
    }

    private function cliente(array $features): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function veiculo(Tenant $tenant): Asset
    {
        return Asset::create(['tenant_id' => $tenant->id, 'name' => 'Carro '.uniqid(), 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'ABC1D23', 'odometro_atual' => 10000, 'ipva_vencimento' => now()->subDays(3)]);
    }

    public function test_sem_nenhum_modulo_da_frota_as_paginas_nao_aparecem_nem_o_contador(): void
    {
        [$tenant, $admin] = $this->cliente(['tabela_assets', 'tabela_asset_downtime_events']);
        $this->veiculo($tenant);
        $this->actingAs($admin);

        $this->assertFalse(PendenciasFrota::canAccess());
        $this->assertFalse(PendenciasFrota::shouldRegisterNavigation());
        $this->assertFalse(FrotaDisponibilidade::canAccess());
        $this->assertNull(PendenciasFrota::getNavigationBadge());
        $this->assertFalse(app(PendenciasFrotaService::class)->temModuloFrota($admin));
    }

    public function test_com_um_modulo_liberado_aparece_so_o_que_pertence_a_ele(): void
    {
        [$tenant, $admin] = $this->cliente(['tabela_assets', 'tabela_frota_multas', 'tabela_asset_downtime_events']);
        $v = $this->veiculo($tenant);
        $this->actingAs($admin);
        (new MultaService)->registrar($v, ['numero_auto' => 'A1', 'infracao_em' => now()->subDay()->toDateTimeString(), 'descricao' => 'x', 'gravidade' => 'leve', 'valor' => 100, 'vencimento' => now()->subDay()->toDateString()]);
        $kit = (new KitSegurancaService)->criar($v, ['nome' => 'Macaco']);
        (new KitSegurancaService)->conferir($kit, ['presente' => false]);   // pendência do kit existe, mas o módulo do kit não está liberado

        $this->assertTrue(PendenciasFrota::canAccess());
        $this->assertTrue(FrotaDisponibilidade::canAccess());
        $categorias = app(PendenciasFrotaService::class)->todasPermitidas($admin)->pluck('categoria')->unique()->sort()->values()->all();

        $this->assertContains('multa', $categorias);
        $this->assertContains('documento', $categorias);   // vencimento de IPVA: módulo de ativos que já existia
        $this->assertNotContains('kit', $categorias);
        $this->assertContains('kit', app(PendenciasFrotaService::class)->todas()->pluck('categoria')->all());
    }

    public function test_contador_do_menu_conta_so_o_visivel_e_a_pagina_nao_lista_o_resto(): void
    {
        [$tenant, $admin] = $this->cliente(['tabela_assets', 'tabela_frota_multas']);
        $v = $this->veiculo($tenant);
        $this->actingAs($admin);
        $kit = (new KitSegurancaService)->criar($v, ['nome' => 'Macaco']);
        (new KitSegurancaService)->conferir($kit, ['presente' => false]);
        Cache::forget('frota-pendencias-contagem:'.$admin->id);

        $visiveis = app(PendenciasFrotaService::class)->todasPermitidas($admin)->count();
        $this->assertSame((string) $visiveis, PendenciasFrota::getNavigationBadge());

        Livewire::test(PendenciasFrota::class)->assertSee('IPVA vencido')->assertDontSee('Macaco ausente');
    }

    public function test_usuario_sem_permissao_do_modulo_nao_ve_mesmo_com_o_plano(): void
    {
        [$tenant, $admin] = $this->cliente(['tabela_assets', 'tabela_frota_multas']);
        $this->veiculo($tenant);
        $comum = User::create(['name' => 'Op', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $comum->forceFill(['email_verified_at' => now()])->save();
        FleetDriver::create(['tenant_id' => $tenant->id, 'name' => 'João', 'active' => true]);
        $this->actingAs($comum);

        $this->assertFalse(PendenciasFrota::canAccess());
        $this->assertSame([], app(PendenciasFrotaService::class)->categoriasPermitidas($comum));
    }
}
