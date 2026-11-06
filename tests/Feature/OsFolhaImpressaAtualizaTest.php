<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceOrderResource;
use App\Filament\Resources\MaintenanceOrderResource\Pages\AtualizarComFolha;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OsFolhaAtualizacaoService;
use App\Services\OsPhotoOcrService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Fluxo: O.S. criada na empresa, impressa pre-preenchida; a IA le a folha devolvida e ATUALIZA a mesma O.S. */
class OsFolhaImpressaAtualizaTest extends TestCase
{
    use DatabaseTransactions;

    private const FOTO = 'data:image/jpeg;base64,ZmFrZS1waG90by1ieXRlcw==';

    private function cenario(): array
    {
        $plan = Plan::create(['name' => 'Plano Folha '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_assets', 'tabela_clients']]);
        $tenant = Tenant::create(['name' => 'Locadora Folha '.uniqid(), 'slug' => 'folha-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin Folha', 'email' => 'folha-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $this->actingAs($admin);
        $cat = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Terraplenagem']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'asset_category_id' => $cat->id, 'name' => 'Escavadeira CAT 320', 'last_horimetro' => 1200, 'status' => Asset::STATUS_DISPONIVEL]);
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Obras Rio Verde Ltda']);
        $os = MaintenanceOrder::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'client_id' => $client->id, 'maintenance_type' => 'Corretiva',
            'description' => 'Vazamento na lança', 'status' => 'Pendente']);

        return [$tenant, $admin, $asset, $client, $os];
    }

    private function fakeIA(array $payload): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => json_encode($payload)]]], 200)]);
    }

    public function test_a_folha_sai_pre_preenchida_com_os_dados_da_os(): void
    {
        [, , , , $os] = $this->cenario();

        $html = $this->get(route('maintenance-orders.folha', $os))->assertOk()->getContent();

        foreach ([$os->os_number, 'Obras Rio Verde Ltda', 'Escavadeira CAT 320', 'Vazamento na lança', '1.200'] as $texto) {
            $this->assertStringContainsString($texto, $html, $texto);
        }
        $this->assertStringContainsString('<svg', $html); // QR
    }

    public function test_outro_tenant_nao_imprime_a_folha(): void
    {
        [, , , , $os] = $this->cenario();
        [, $estranho] = $this->cenario();
        $this->actingAs($estranho);

        $this->assertContains($this->get(route('maintenance-orders.folha', $os))->getStatusCode(), [403, 404]);
    }

    public function test_resolve_update_le_servico_horimetro_horas_e_avisa_numero_diferente(): void
    {
        [, , , , $os] = $this->cenario();

        $r = app(OsPhotoOcrService::class)->resolveUpdate([
            'service_done' => 'Troca da mangueira', 'horimetro' => 1250, 'hours_spent' => 2.5, 'paper_number' => '9999',
            'started_at' => '2026-11-06 08:00', 'finished_at' => '2026-11-06 10:30',
        ], $os);

        $this->assertSame('Troca da mangueira', $r['campos']['solution']);
        $this->assertSame(1250.0, $r['campos']['horimetro_entry']);
        $this->assertSame(9000, $r['campos']['total_time_seconds']);
        $this->assertFalse($r['numero_confere']);
        $this->assertNotEmpty(array_filter($r['avisos'], fn ($a) => str_contains($a, 'não é o desta O.S.')));
    }

    public function test_aplicar_atualiza_a_mesma_os_sem_criar_outra_e_nao_troca_cliente_nem_equipamento(): void
    {
        [, $admin, $asset, $client, $os] = $this->cenario();
        $this->fakeIA(['service_done' => 'Troca da mangueira', 'horimetro' => 1250, 'hours_spent' => 2, 'paper_number' => $os->os_number, 'observations' => 'Tudo ok']);
        $antes = MaintenanceOrder::count();

        $svc = app(OsFolhaAtualizacaoService::class);
        $r = $svc->preparar($os, self::FOTO);
        $this->assertTrue($r['ok']);
        $svc->aplicar($os, $r['preparo'], ['campos' => array_keys($r['preparo']['campos']), 'pecas' => false, 'status' => 'Concluída'], null, $admin);

        $os->refresh();
        $this->assertSame($antes, MaintenanceOrder::count());
        $this->assertSame('Troca da mangueira', $os->solution);
        $this->assertSame('Concluída', $os->status);
        $this->assertSame($asset->id, $os->asset_id);
        $this->assertSame($client->id, $os->client_id);
        $this->assertNotNull($svc->jaAplicadaEm($os));
    }

    public function test_so_grava_os_campos_aprovados(): void
    {
        [, $admin, , , $os] = $this->cenario();
        $this->fakeIA(['service_done' => 'Servico X', 'observations' => 'Obs Y', 'paper_number' => $os->os_number]);

        $svc = app(OsFolhaAtualizacaoService::class);
        $r = $svc->preparar($os, self::FOTO);
        $svc->aplicar($os, $r['preparo'], ['campos' => ['solution']], null, $admin);

        $os->refresh();
        $this->assertSame('Servico X', $os->solution);
        $this->assertStringNotContainsString('Obs Y', (string) $os->technical_notes);
    }

    public function test_folha_sem_nada_legivel_nao_propoe_atualizacao(): void
    {
        [, , , , $os] = $this->cenario();
        $this->fakeIA(['paper_number' => $os->os_number]);

        $r = app(OsFolhaAtualizacaoService::class)->preparar($os, self::FOTO);

        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Não consegui ler', $r['error']);
    }

    public function test_pagina_de_atualizar_exige_permissao_de_editar(): void
    {
        [, , , , $os] = $this->cenario();
        [, $estranho] = $this->cenario();
        $this->actingAs($estranho);

        $this->assertContains($this->get(MaintenanceOrderResource::getUrl('atualizar-folha', ['record' => $os]))->getStatusCode(), [403, 404]);
    }

    public function test_fluxo_da_pagina_ler_revisar_aplicar_e_bloquear_reaplicacao(): void
    {
        [, , , , $os] = $this->cenario();
        $this->fakeIA(['service_done' => 'Troca da mangueira', 'paper_number' => $os->os_number]);

        $t = Livewire::test(AtualizarComFolha::class, ['record' => $os->getRouteKey()])
            ->set('data.foto', self::FOTO)
            ->call('lerComIA')
            ->assertSet('camposEscolhidos', ['solution'])
            ->call('aplicar');

        $this->assertSame('Troca da mangueira', $os->fresh()->solution);

        $t2 = Livewire::test(AtualizarComFolha::class, ['record' => $os->getRouteKey()])
            ->set('data.foto', self::FOTO)
            ->call('lerComIA');
        $os->forceFill(['solution' => 'mudou'])->save();
        $t2->call('aplicar'); // ja aplicada e sem confirmar: nao grava
        $this->assertSame('mudou', $os->fresh()->solution);
    }
}
