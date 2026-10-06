<?php

namespace Tests\Feature;

use App\Filament\Pages\PendenciasFrota;
use App\Models\Asset;
use App\Models\FrotaBateria;
use App\Models\FrotaChecklist;
use App\Models\FrotaPneu;
use App\Models\MaintenanceOrder;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Frota\BateriaService;
use App\Services\Frota\CatalogoEstoqueFrota;
use App\Services\Frota\OleoService;
use App\Services\Frota\PendenciasFrotaService;
use App\Services\Frota\PneuService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** Gestão de Frota, Fase 5: Pendências da Frota (calculadas ao vivo) e Gerar OS. */
class FrotaPendenciasTest extends TestCase
{
    use DatabaseTransactions;

    private function cliente(): array
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_frota_checklists', 'tabela_maintenance_orders', 'tabela_parts']]);
        $tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 't-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function veiculo(Tenant $tenant, array $extra = []): Asset
    {
        return Asset::create(array_merge(['tenant_id' => $tenant->id, 'name' => 'Caminhão '.uniqid(), 'tag' => 'V-'.uniqid(), 'status' => Asset::STATUS_DISPONIVEL,
            'grupo' => Asset::GRUPO_VEICULO, 'placa' => 'ABC1D23', 'odometro_atual' => 10000], $extra));
    }

    private function mensagens($lista): string
    {
        return $lista->pluck('mensagem')->implode(' | ');
    }

    public function test_documento_vencido_e_critico_a_vencer_e_atencao_e_longe_nao_aparece(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant, [
            'ipva_vencimento' => now()->subDays(3), 'seguro_vencimento' => now()->addDays(10), 'licenciamento_vencimento' => now()->addDays(200),
        ]);

        $docs = (new PendenciasFrotaService)->doVeiculo($v)->where('categoria', 'documento');

        $this->assertSame(2, $docs->count());
        $this->assertSame('critica', $docs->first(fn ($p) => str_contains($p['mensagem'], 'IPVA'))['gravidade']);
        $this->assertSame('atencao', $docs->first(fn ($p) => str_contains($p['mensagem'], 'Seguro'))['gravidade']);
    }

    public function test_oleo_vencido_pneu_e_bateria_entram_na_lista_do_veiculo(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);
        $oleo = new OleoService;
        $oleo->salvarPlano($v, ['intervalo_km' => 1000]);
        $oleo->registrar($v, ['tipo' => 'troca', 'litros' => 20, 'produto' => '15W40', 'odometro' => 10000]);
        $v->update(['odometro_atual' => 11500]);

        $pneu = FrotaPneu::create(['tenant_id' => $tenant->id, 'numero_fogo' => 'F1', 'sulco_inicial_mm' => 1.0]);
        (new PneuService)->montar($pneu, $v->fresh(), 'E1-LE', 11500, $admin);
        (new PneuService)->registrarInspecao($pneu->fresh(), 1.0, null);
        $bat = FrotaBateria::create(['tenant_id' => $tenant->id, 'marca' => 'M']);
        (new BateriaService)->instalar($bat, $v->fresh(), 11500, $admin);
        (new BateriaService)->registrarTeste($bat->fresh(), 11.5);

        $m = $this->mensagens((new PendenciasFrotaService)->doVeiculo($v->fresh()));

        $this->assertStringContainsString('óleo', mb_strtolower($m));
        $this->assertStringContainsString('Pneu F1', $m);
        $this->assertStringContainsString('Bateria', $m);
    }

    public function test_checklist_sem_completo_recente_gera_atencao(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant);

        $p = (new PendenciasFrotaService)->doVeiculo($v)->firstWhere('categoria', 'checklist');

        $this->assertSame('atencao', $p['gravidade']);
        $this->assertStringContainsString('checklist completo', $p['mensagem']);
    }

    public function test_estoque_abaixo_do_minimo_so_para_itens_da_frota(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        (new CatalogoEstoqueFrota)->garantir($tenant->id);
        $arla = Part::where('name', 'Arla 32')->first();
        $alm = Warehouse::create(['tenant_id' => $tenant->id, 'name' => 'Central', 'is_active' => true]);
        WarehouseStock::create(['warehouse_id' => $alm->id, 'part_id' => $arla->id, 'current_quantity' => 50, 'reserved_quantity' => 0]);
        $outra = Part::create(['tenant_id' => $tenant->id, 'part_category_id' => PartCategory::create(['tenant_id' => $tenant->id, 'name' => 'Outra'])->id,
            'sku' => 'X'.uniqid(), 'name' => 'Peça de outra área', 'unit_of_measure' => 'UN', 'minimum_stock' => 5]);

        $m = $this->mensagens((new PendenciasFrotaService)->estoqueBaixo());

        $this->assertStringContainsString('Arla 32 abaixo do mínimo: saldo 50', $m);
        $this->assertStringNotContainsString($outra->name, $m);
    }

    public function test_gerar_os_abre_uma_so_enquanto_estiver_aberta(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant, ['ipva_vencimento' => now()->subDay()]);
        $servico = new PendenciasFrotaService;
        $pendencia = $servico->doVeiculo($v)->firstWhere('categoria', 'documento');

        $os = $servico->gerarOs($pendencia);

        $this->assertSame($v->id, $os->asset_id);
        $this->assertStringContainsString('IPVA', $os->description);
        $this->assertSame($os->id, $servico->osAberta($pendencia['chave'])->id);
        try {
            $servico->gerarOs($pendencia);
            $this->fail('Deveria recusar a segunda OS.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('os', $e->errors());
        }

        $os->update(['status' => 'Concluída']);
        $this->assertNull($servico->osAberta($pendencia['chave']));
    }

    public function test_pendencia_de_estoque_nao_gera_os_de_veiculo(): void
    {
        $this->expectException(ValidationException::class);
        (new PendenciasFrotaService)->gerarOs(['chave' => 'x', 'categoria' => 'estoque', 'ativo_id' => null, 'mensagem' => 'm']);
    }

    public function test_um_cliente_nao_ve_pendencias_do_outro(): void
    {
        [$a, $adminA] = $this->cliente();
        [$b, $adminB] = $this->cliente();
        $this->veiculo($a, ['ipva_vencimento' => now()->subDay()]);

        $this->actingAs($adminB);
        $this->assertSame(0, (new PendenciasFrotaService)->todas()->where('categoria', 'documento')->count());
        $this->actingAs($adminA);
        $this->assertSame(1, (new PendenciasFrotaService)->todas()->where('categoria', 'documento')->count());
    }

    public function test_pagina_lista_filtra_e_gera_os(): void
    {
        [$tenant, $admin] = $this->cliente();
        $this->actingAs($admin);
        $v = $this->veiculo($tenant, ['ipva_vencimento' => now()->subDay()]);
        $chave = (new PendenciasFrotaService)->doVeiculo($v)->firstWhere('categoria', 'documento')['chave'];

        Livewire::test(PendenciasFrota::class)
            ->assertSee('IPVA vencido')
            ->set('filtroCategoria', 'pneu')
            ->assertDontSee('IPVA vencido')
            ->set('filtroCategoria', null)
            ->call('gerarOs', $chave)
            ->assertSee('OS '.MaintenanceOrder::where('asset_id', $v->id)->value('os_number').' aberta');

        $this->assertSame(1, MaintenanceOrder::where('asset_id', $v->id)->where('description', 'like', "[Frota:{$chave}]%")->count());
    }

    public function test_perfil_com_permissao_especial_libera_e_sem_ela_nao(): void
    {
        [$tenant, $admin] = $this->cliente();
        $comum = User::create(['name' => 'Op', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $comum->forceFill(['email_verified_at' => now()])->save();
        $checklist = new FrotaChecklist(['tenant_id' => $tenant->id]);
        $this->assertFalse($comum->can('liberar', $checklist));

        $comum->givePermissionTo(Permission::firstOrCreate(['name' => 'liberar_checklist_frota', 'guard_name' => 'web']));
        $this->assertTrue($comum->fresh()->can('liberar', $checklist));
    }
}
