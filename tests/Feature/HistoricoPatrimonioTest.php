<?php

namespace Tests\Feature;

use App\Filament\Pages\HistoricoPatrimonio;
use App\Models\Asset;
use App\Models\EquipmentDamage;
use App\Models\EquipmentReplacement;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderPendencia;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido do usuário a partir do Painel de Criticidade: histórico
 * unificado por Patrimônio cruzando avarias, OS, pendências e trocas --
 * mesmo padrão de métricas/gráficos do Dashboard PMP, filtrável por
 * Ativo, intervalo de data e tipo de evento.
 */
class HistoricoPatrimonioTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Historico '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Historico '.uniqid(), 'slug' => 'tenant-historico-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_aggregates_events_from_all_four_sources_with_correct_type_tags(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Histórico', 'status' => Asset::STATUS_DISPONIVEL]);
        $outroAsset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Outro Ativo', 'status' => Asset::STATUS_DISPONIVEL]);

        // OS preventiva -> "ordens_de_servico" + "preventivas" (também serve
        // de maintenance_order_id obrigatório pras avarias abaixo).
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Preventiva',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);

        // Avaria grave -> "criticidade"; avaria leve -> "problemas_reportados"
        EquipmentDamage::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_order_id' => $order->id,
            'reported_by_user_id' => $admin->id, 'severity' => EquipmentDamage::SEVERITY_GRAVE,
            'damage_type' => EquipmentDamage::DAMAGE_TYPE_MOTOR, 'cause' => EquipmentDamage::CAUSE_DESGASTE_NATURAL,
            'description' => 'Motor com falha grave', 'status' => EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR,
        ]);
        EquipmentDamage::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_order_id' => $order->id,
            'reported_by_user_id' => $admin->id, 'severity' => EquipmentDamage::SEVERITY_LEVE,
            'damage_type' => EquipmentDamage::DAMAGE_TYPE_OUTRO, 'cause' => EquipmentDamage::CAUSE_DESGASTE_NATURAL,
            'description' => 'Risco na pintura', 'status' => EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR,
        ]);

        // Pendência dessa OS -> "pendencias"
        MaintenanceOrderPendencia::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id,
            'description' => 'Falta peça X', 'created_by_user_id' => $admin->id,
            'status' => MaintenanceOrderPendencia::STATUS_ABERTA,
        ]);

        // Troca envolvendo o ativo como original -> "trocas"
        EquipmentReplacement::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id,
            'original_asset_id' => $asset->id, 'replacement_asset_id' => $outroAsset->id,
            'requested_by_user_id' => $admin->id, 'urgency' => 'normal', 'reason' => 'Quebra em obra',
            'status' => 'solicitado',
        ]);

        // Evento de OUTRO ativo -- não pode aparecer no histórico deste.
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $outroAsset->id, 'description' => 'OS de outro ativo',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        $page = new HistoricoPatrimonio;
        $page->mount($asset->id);

        $events = $page->getAllEvents();

        $this->assertCount(5, $events);

        $tiposAchados = $events->pluck('tipos')->flatten()->unique()->sort()->values()->all();
        sort($tiposAchados);
        $this->assertSame(
            ['criticidade', 'ordens_de_servico', 'pendencias', 'preventivas', 'problemas_reportados', 'trocas'],
            $tiposAchados
        );

        // Nenhum evento do outro ativo vazou.
        $this->assertFalse($events->contains('title', 'Ordem de Serviço aberta (Corretiva)'));
    }

    public function test_tipo_filter_narrows_events(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Filtro', 'status' => Asset::STATUS_DISPONIVEL]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Corretiva',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);
        EquipmentDamage::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_order_id' => $order->id,
            'reported_by_user_id' => $admin->id, 'severity' => EquipmentDamage::SEVERITY_GRAVE,
            'damage_type' => EquipmentDamage::DAMAGE_TYPE_MOTOR, 'cause' => EquipmentDamage::CAUSE_DESGASTE_NATURAL,
            'description' => 'Falha grave', 'status' => EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR,
        ]);

        $page = new HistoricoPatrimonio;
        $page->mount($asset->id);

        $page->tipo = 'criticidade';
        $this->assertCount(1, $page->getFilteredEvents());

        $page->tipo = 'corretivas';
        $this->assertCount(1, $page->getFilteredEvents());

        $page->tipo = 'trocas';
        $this->assertCount(0, $page->getFilteredEvents());

        $page->tipo = 'todos';
        $this->assertCount(2, $page->getFilteredEvents());
    }

    public function test_date_range_filter_excludes_events_outside_window(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Data', 'status' => Asset::STATUS_DISPONIVEL]);

        $antigo = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'OS antiga',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);
        $antigo->forceFill(['created_at' => now()->subYear()])->save();

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'OS recente',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        $page = new HistoricoPatrimonio;
        $page->mount($asset->id);
        $page->dateFrom = now()->subMonth()->toDateString();
        $page->dateTo = now()->toDateString();

        $events = $page->getFilteredEvents();

        $this->assertCount(1, $events);
        $this->assertTrue($events->first()['at']->gte(now()->subMonth()));
    }

    public function test_page_renders_and_search_redirects_to_single_match(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Único', 'patrimonio' => 'PAT-HIST-01', 'status' => Asset::STATUS_DISPONIVEL]);

        $this->get(HistoricoPatrimonio::getUrl(['assetId' => $asset->id]))
            ->assertOk()
            ->assertSee('Guindaste Único')
            ->assertSee('Total de Eventos');

        Livewire::test(HistoricoPatrimonio::class)
            ->set('query', 'PAT-HIST-01')
            ->call('search')
            ->assertRedirect(HistoricoPatrimonio::getUrl(['assetId' => $asset->id]));
    }
}
