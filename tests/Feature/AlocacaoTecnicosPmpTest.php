<?php

namespace Tests\Feature;

use App\Filament\Pages\AlocacaoTecnicosPmp;
use App\Models\Asset;
use App\Models\Client;
use App\Models\MaintenanceDueAlert;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Role;
use App\Models\TechnicianAllocation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSpecialty;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AlocacaoTecnicosPmpTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Alocacao Page '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Alocacao Page '.uniqid(), 'slug' => 'tenant-alocacao-page-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin Alocacao', 'email' => 'admin-alocacao-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_corrective_order_without_technician_appears_in_queue_as_unallocated(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Fila', 'status' => Asset::STATUS_DISPONIVEL]);
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'description' => 'Falha elétrica', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'failure_category' => MaintenanceOrder::FAILURE_CATEGORY_ELETRICO,
            'internal_status' => 'aguardando_diagnostico',
        ]);

        $this->actingAs($admin);

        $page = Livewire::test(AlocacaoTecnicosPmp::class);
        $queue = $page->instance()->queueItems;

        $this->assertCount(1, $queue);
        $this->assertFalse($queue->first()['allocated']);
        $this->assertSame(MaintenanceOrder::FAILURE_CATEGORY_ELETRICO, $queue->first()['failure_category']);
    }

    public function test_preventive_order_planned_via_pmp_kanban_appears_in_queue_as_unallocated(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Preventiva Fila', 'status' => Asset::STATUS_DISPONIVEL]);
        $plan = MaintenancePlan::create(['tenant_id' => $tenant->id, 'name' => 'Plano Troca de Óleo', 'asset_id' => $asset->id]);

        // Mesmo internal_status gravado por PainelPmp::updateOrderColumn()
        // quando o card entra na coluna "Planejado" (COLUMN_TO_ORDER_STATUS).
        MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'maintenance_plan_id' => $plan->id,
            'internal_status' => 'aguardando_diagnostico',
            'status' => 'Aberto',
        ]);

        $this->actingAs($admin);

        $page = Livewire::test(AlocacaoTecnicosPmp::class);
        $queue = $page->instance()->queueItems;

        $this->assertCount(1, $queue);
        $this->assertFalse($queue->first()['allocated']);
        $this->assertNull($queue->first()['failure_category']);
        $this->assertStringContainsString('Plano Troca de Óleo', $queue->first()['title']);
    }

    public function test_allocate_creates_technician_allocation_and_syncs_order(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Alocar', 'status' => Asset::STATUS_DISPONIVEL]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'description' => 'Falha mecânica', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'failure_category' => MaintenanceOrder::FAILURE_CATEGORY_MOTOR,
            'internal_status' => 'aguardando_diagnostico',
        ]);
        $technician = User::create([
            'name' => 'Tecnico Alocar', 'email' => 'tec-alocar-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(AlocacaoTecnicosPmp::class)
            ->call('allocate', 'os:'.$order->id, $technician->id, now()->addDay()->toDateTimeString());

        $allocation = TechnicianAllocation::where('maintenance_order_id', $order->id)->sole();
        $this->assertSame($technician->id, $allocation->technician_id);

        $order->refresh();
        $this->assertSame($technician->id, $order->technician_id);
    }

    public function test_allocating_technician_without_specialty_warns_but_does_not_block(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Sem Especialidade', 'status' => Asset::STATUS_DISPONIVEL]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'description' => 'Falha hidráulica', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'failure_category' => MaintenanceOrder::FAILURE_CATEGORY_HIDRAULICO,
            'internal_status' => 'aguardando_diagnostico',
        ]);
        $technician = User::create([
            'name' => 'Tecnico Eletricista', 'email' => 'tec-eletricista-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        UserSpecialty::create(['user_id' => $technician->id, 'specialty' => MaintenanceOrder::FAILURE_CATEGORY_ELETRICO]);

        $this->actingAs($admin);

        Livewire::test(AlocacaoTecnicosPmp::class)
            ->call('allocate', 'os:'.$order->id, $technician->id, now()->addDay()->toDateTimeString());

        // Não bloqueou -- a alocação foi criada mesmo com especialidade
        // incompatível, filosofia de aviso-nunca-bloqueio confirmada com o
        // usuário.
        $this->assertSame(1, TechnicianAllocation::where('maintenance_order_id', $order->id)->count());
    }

    public function test_allocating_alert_creates_allocation_without_maintenance_order(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Alerta', 'status' => Asset::STATUS_DISPONIVEL,
            'horimetro_atual' => 500,
        ]);
        $plano = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Plano Alerta',
            'interval_hours' => 250, 'last_service_hours' => 0,
        ]);
        $alert = MaintenanceDueAlert::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_plan_id' => $plano->id,
            'alerted_at' => now(),
        ]);
        $technician = User::create([
            'name' => 'Tecnico Alerta', 'email' => 'tec-alerta-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        $this->actingAs($admin);

        Livewire::test(AlocacaoTecnicosPmp::class)
            ->call('allocate', 'alert:'.$alert->id, $technician->id, now()->addDay()->toDateTimeString());

        $allocation = TechnicianAllocation::where('maintenance_due_alert_id', $alert->id)->sole();
        $this->assertSame($technician->id, $allocation->technician_id);
        $this->assertNull($allocation->maintenance_order_id);
    }

    /**
     * Pedido do usuário 2026-08-28: card do Gantt precisa de um botão de
     * confirmar ali mesmo, pro analista confirmar em nome do técnico --
     * diferente de TechnicianDailyTasks::confirmAllocation(), que é o
     * próprio técnico confirmando (escopado por Auth::id()).
     */
    public function test_confirm_allocation_from_gantt_changes_status(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Confirmar Gantt', 'status' => Asset::STATUS_DISPONIVEL]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'description' => 'Corretiva confirmar gantt', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'internal_status' => 'aguardando_diagnostico',
        ]);
        $technician = User::create([
            'name' => 'Tecnico Confirmar Gantt', 'email' => 'tec-confirmar-gantt-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $allocation = TechnicianAllocation::create([
            'tenant_id' => $tenant->id, 'technician_id' => $technician->id, 'maintenance_order_id' => $order->id,
            'starts_at' => now(), 'ends_at' => now()->addHours(2),
        ]);

        $this->actingAs($admin);

        Livewire::test(AlocacaoTecnicosPmp::class)
            ->call('confirmAllocation', $allocation->id);

        $allocation->refresh();
        $this->assertSame(TechnicianAllocation::STATUS_CONFIRMADO, $allocation->status);
    }

    /**
     * Pedido do usuário 2026-08-28: filtros de cliente/técnico/patrimônio
     * ao lado dos controles de período, afetando o que o Gantt mostra.
     */
    public function test_filters_narrow_down_visible_allocations(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $clientA = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente A']);
        $clientB = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente B']);

        $assetA = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo A', 'patrimonio' => 'PAT-AAA', 'status' => Asset::STATUS_DISPONIVEL, 'client_id' => $clientA->id]);
        $assetB = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo B', 'patrimonio' => 'PAT-BBB', 'status' => Asset::STATUS_DISPONIVEL, 'client_id' => $clientB->id]);

        $orderA = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetA->id, 'description' => 'Corretiva A',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);
        $orderB = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetB->id, 'description' => 'Corretiva B',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        $technicianA = User::create([
            'name' => 'Tecnico A', 'email' => 'tec-filtro-a-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $technicianB = User::create([
            'name' => 'Tecnico B', 'email' => 'tec-filtro-b-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        TechnicianAllocation::create([
            'tenant_id' => $tenant->id, 'technician_id' => $technicianA->id, 'maintenance_order_id' => $orderA->id,
            'starts_at' => now(), 'ends_at' => now()->addHours(2),
        ]);
        TechnicianAllocation::create([
            'tenant_id' => $tenant->id, 'technician_id' => $technicianB->id, 'maintenance_order_id' => $orderB->id,
            'starts_at' => now(), 'ends_at' => now()->addHours(2),
        ]);

        $this->actingAs($admin);

        // Filtro por cliente
        $page = Livewire::test(AlocacaoTecnicosPmp::class)->set('filterClientId', $clientA->id);
        $this->assertCount(1, $page->instance()->allocations);
        $this->assertSame($orderA->id, $page->instance()->allocations->first()->maintenance_order_id);

        // Filtro por técnico
        $page = Livewire::test(AlocacaoTecnicosPmp::class)->set('filterTechnicianId', $technicianB->id);
        $this->assertCount(1, $page->instance()->allocations);
        $this->assertSame($technicianB->id, $page->instance()->allocations->first()->technician_id);

        // Filtro por patrimônio
        $page = Livewire::test(AlocacaoTecnicosPmp::class)->set('filterPatrimonio', 'bbb');
        $this->assertCount(1, $page->instance()->allocations);
        $this->assertSame($orderB->id, $page->instance()->allocations->first()->maintenance_order_id);
    }

    public function test_technician_summary_counts_allocated_pending_and_confirmed(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Resumo', 'status' => Asset::STATUS_DISPONIVEL]);
        $technician = User::create([
            'name' => 'Tecnico Resumo', 'email' => 'tec-resumo-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        $pendingOrder = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Pendente',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);
        $confirmedOrder = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Confirmada',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        TechnicianAllocation::create([
            'tenant_id' => $tenant->id, 'technician_id' => $technician->id, 'maintenance_order_id' => $pendingOrder->id,
            'starts_at' => now(), 'ends_at' => now()->addHours(2),
        ]);
        TechnicianAllocation::create([
            'tenant_id' => $tenant->id, 'technician_id' => $technician->id, 'maintenance_order_id' => $confirmedOrder->id,
            'starts_at' => now(), 'ends_at' => now()->addHours(2),
            'status' => TechnicianAllocation::STATUS_CONFIRMADO,
        ]);

        $this->actingAs($admin);

        $page = Livewire::test(AlocacaoTecnicosPmp::class)->instance();
        $row = $page->technicianSummary->firstWhere('technician.id', $technician->id);

        $this->assertSame(2, $row['alocados']);
        $this->assertSame(1, $row['aguardando']);
        $this->assertSame(1, $row['confirmados']);

        $totals = $page->technicianSummaryTotals;
        $this->assertSame(2, $totals['alocados']);
        $this->assertSame(1, $totals['aguardando']);
        $this->assertSame(1, $totals['confirmados']);
    }
}
