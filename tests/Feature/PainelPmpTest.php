<?php

namespace Tests\Feature;

use App\Filament\Pages\PainelPmp;
use App\Models\Asset;
use App\Models\Client;
use App\Models\MaintenanceDueAlert;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\MaintenanceStatusHistory;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PainelPmpTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano PMP '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_maintenance_plans'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant PMP '.uniqid(), 'slug' => 'tenant-pmp-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin PMP', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_page_renders_with_kpis_and_kanban_columns(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador PMP', 'patrimonio' => 'PAT-PMP-01', 'status' => 'disponivel']);

        foreach (['aguardando_diagnostico', 'em_manutencao', 'teste_qualidade', 'concluido'] as $status) {
            MaintenanceOrder::create([
                'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
                'description' => 'Preventiva '.$status, 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
                'internal_status' => $status,
            ]);
        }

        $this->actingAs($admin);

        $response = $this->get(PainelPmp::getUrl());

        $response->assertOk();
        $response->assertSee('A Fazer (Para Planejar)');
        $response->assertSee('Planejado');
        $response->assertSee('Em Andamento');
        $response->assertSee('Em Revisão');
        $response->assertSee('Concluído');
        $response->assertSee('Gerador PMP');
    }

    /**
     * KPI "Ordens de Serviço Totais" só conta preventivas -- uma corretiva
     * junto não pode inflar o número (bug fácil de introduzir se alguém
     * tirar o filtro maintenance_type da query base).
     */
    public function test_kpis_only_count_preventive_orders(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo KPI', 'patrimonio' => 'PAT-KPI', 'status' => 'disponivel']);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Preventiva 1',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE, 'internal_status' => 'concluido',
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Corretiva 1',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'concluido',
        ]);

        $this->actingAs($admin);

        $page = new PainelPmp;
        $kpis = $page->getKpis();

        $this->assertSame(1, $kpis['total']);
        $this->assertSame(1, $kpis['concluidas']);
    }

    public function test_moving_alert_card_creates_real_preventive_order_linked_to_plan(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Guindaste Alerta', 'patrimonio' => 'PAT-ALERT',
            'status' => 'disponivel', 'horimetro_atual' => 500,
        ]);
        $plano = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Troca de óleo',
            'interval_hours' => 250, 'last_service_hours' => 0,
        ]);
        $alert = MaintenanceDueAlert::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_plan_id' => $plano->id,
            'alerted_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(PainelPmp::class)
            ->call('moveCard', 'alert:'.$alert->id, PainelPmp::COL_PLANEJADO);

        $order = MaintenanceOrder::where('tenant_id', $tenant->id)
            ->where('maintenance_plan_id', $plano->id)
            ->sole();

        $this->assertSame($asset->id, $order->asset_id);
        $this->assertSame(MaintenanceOrder::TYPE_PREVENTIVE, $order->maintenance_type);
        $this->assertSame('aguardando_diagnostico', $order->internal_status);

        // A OS agora existe pra esse par Ativo+Plano -- o alerta não pode
        // mais aparecer na coluna "A Fazer" (senão duplicaria o card).
        $page = new PainelPmp;
        $this->assertCount(0, $page->getPendingAlerts());
    }

    public function test_moving_order_card_updates_internal_status_and_logs_history(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Kanban PMP', 'patrimonio' => 'PAT-KB', 'status' => 'disponivel']);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Preventiva planejada',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE, 'internal_status' => 'aguardando_diagnostico',
            'status' => 'Aberto',
        ]);

        $this->actingAs($admin);

        Livewire::test(PainelPmp::class)
            ->call('moveCard', 'os:'.$order->id, PainelPmp::COL_EM_ANDAMENTO);

        $order->refresh();
        $this->assertSame('em_manutencao', $order->internal_status);
        $this->assertSame('Em Andamento', $order->status);
        $this->assertNotNull($order->started_at);

        $history = MaintenanceStatusHistory::where('maintenance_order_id', $order->id)->sole();
        $this->assertSame('aguardando_diagnostico', $history->old_status);
        $this->assertSame('em_manutencao', $history->new_status);
        $this->assertSame($admin->id, $history->user_id);
    }

    public function test_moving_order_to_concluido_sets_status_and_finished_at(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Concl PMP', 'patrimonio' => 'PAT-CC', 'status' => 'disponivel']);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Preventiva em revisão',
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE, 'internal_status' => 'teste_qualidade',
            'status' => 'Em Andamento',
        ]);

        $this->actingAs($admin);

        Livewire::test(PainelPmp::class)
            ->call('moveCard', 'os:'.$order->id, PainelPmp::COL_CONCLUIDO);

        $order->refresh();
        $this->assertSame('concluido', $order->internal_status);
        $this->assertSame('Concluída', $order->status);
        $this->assertNotNull($order->finished_at);
    }

    /**
     * Clicar num card de KPI abre a lista de equipamentos daquele grupo
     * com a localização (Asset.endereco) -- pra apoiar a decisão de qual
     * técnico mandar pra onde.
     */
    public function test_clicking_kpi_shows_equipment_list_with_location(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Guindaste Obra Norte', 'patrimonio' => 'PAT-LOC',
            'status' => 'disponivel', 'endereco' => 'Av. das Nações, 1200 - Campinas/SP',
        ]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Preventiva em andamento', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'internal_status' => 'em_manutencao', 'status' => 'Em Andamento',
        ]);

        $this->actingAs($admin);

        $page = Livewire::test(PainelPmp::class)
            ->call('openKpiList', 'em_andamento')
            ->assertSet('openKpiGroup', 'em_andamento')
            ->assertSee('Guindaste Obra Norte')
            ->assertSee('Av. das Nações, 1200 - Campinas/SP');

        $items = $page->instance()->getKpiListItems();
        $this->assertCount(1, $items);
        $this->assertSame($order->os_number, $items->first()['os_number']);

        $page->call('closeKpiList')->assertSet('openKpiGroup', null);
    }

    public function test_kpi_list_falls_back_to_client_name_when_asset_has_no_address(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Obra Sul']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Sem Endereço', 'patrimonio' => 'PAT-SEM', 'status' => 'disponivel']);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'client_id' => $client->id,
            'description' => 'Preventiva bloqueada', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'internal_status' => 'aguardando_peca', 'status' => 'Em Andamento',
        ]);

        $this->actingAs($admin);

        $page = new PainelPmp;
        $page->openKpiGroup = 'criticas';
        $items = $page->getKpiListItems();

        $this->assertSame('Cliente Obra Sul', $items->first()['location']);
    }

    /**
     * Filtros globais (pedido do usuário 2026-08-26): técnico, cliente,
     * período e status afetam getKpis() -- consumido também por
     * getKanbanColumns()/getPendingAlerts()/getCriticalAlerts(), então
     * testar aqui cobre o ponto único de filtragem (baseOrdersQuery()).
     */
    public function test_technician_filter_only_counts_that_technicians_orders(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Filtro Tec', 'patrimonio' => 'PAT-FT', 'status' => 'disponivel']);

        $technicianA = User::create([
            'name' => 'Tecnico A', 'email' => 'tec-a-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $technicianB = User::create([
            'name' => 'Tecnico B', 'email' => 'tec-b-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $technicianA->id,
            'description' => 'OS Tecnico A', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'internal_status' => 'concluido',
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $technicianB->id,
            'description' => 'OS Tecnico B', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'internal_status' => 'concluido',
        ]);

        $this->actingAs($admin);

        $page = new PainelPmp;
        $page->filterTechnicianId = $technicianA->id;

        $this->assertSame(1, $page->getKpis()['total']);
    }

    public function test_client_filter_only_counts_that_clients_orders(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Filtro Cli', 'patrimonio' => 'PAT-FC', 'status' => 'disponivel']);

        $clientA = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Filtro A']);
        $clientB = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Filtro B']);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'client_id' => $clientA->id,
            'description' => 'OS Cliente A', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'internal_status' => 'concluido',
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'client_id' => $clientB->id,
            'description' => 'OS Cliente B', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'internal_status' => 'concluido',
        ]);

        $this->actingAs($admin);

        $page = new PainelPmp;
        $page->filterClientId = $clientA->id;

        $this->assertSame(1, $page->getKpis()['total']);
    }

    public function test_period_filter_restricts_by_scheduled_at(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Filtro Periodo', 'patrimonio' => 'PAT-FP', 'status' => 'disponivel']);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'description' => 'OS Esta Semana', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'internal_status' => 'concluido', 'scheduled_at' => now(),
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'description' => 'OS Mes Que Vem', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'internal_status' => 'concluido', 'scheduled_at' => now()->addMonth(),
        ]);

        $this->actingAs($admin);

        $page = new PainelPmp;
        $page->filterPeriod = 'week';

        $this->assertSame(1, $page->getKpis()['total']);
    }

    public function test_status_filter_pendente_only_counts_awaiting_orders(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Filtro Status', 'patrimonio' => 'PAT-FS', 'status' => 'disponivel']);

        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'description' => 'OS Pendente', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'internal_status' => 'aguardando_diagnostico',
        ]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'description' => 'OS Concluida', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'internal_status' => 'concluido',
        ]);

        $this->actingAs($admin);

        $page = new PainelPmp;
        $page->filterStatus = 'pendente';

        $this->assertSame(1, $page->getKpis()['total']);
    }

    public function test_invalid_move_does_not_change_any_data(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Guindaste Inválido', 'patrimonio' => 'PAT-INV',
            'status' => 'disponivel', 'horimetro_atual' => 500,
        ]);
        $plano = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Filtro de ar',
            'interval_hours' => 250, 'last_service_hours' => 0,
        ]);
        $alert = MaintenanceDueAlert::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_plan_id' => $plano->id,
            'alerted_at' => now(),
        ]);

        $this->actingAs($admin);

        // Um "alert:" card só pode ir pra "Planejado" -- soltar em qualquer
        // outra coluna não deve criar OS nenhuma.
        Livewire::test(PainelPmp::class)
            ->call('moveCard', 'alert:'.$alert->id, PainelPmp::COL_EM_ANDAMENTO);

        $this->assertSame(0, MaintenanceOrder::where('tenant_id', $tenant->id)->count());
    }
}
