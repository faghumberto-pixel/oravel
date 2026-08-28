<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\MaintenanceDueAlert;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\TechnicianAllocation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-08-28: card do Gantt precisa mostrar PAT + tipo
 * de manutenção, não o nome genérico "Alocação".
 */
class TechnicianAllocationDisplayLabelTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAndTechnician(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Display Label '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Display Label '.uniqid(), 'slug' => 'tenant-display-label-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $technician = User::create([
            'name' => 'Tecnico Display Label', 'email' => 'tecnico-display-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        return [$tenant, $technician];
    }

    public function test_label_for_preventive_order_shows_pat_and_plan_name(): void
    {
        [$tenant, $technician] = $this->makeTenantAndTechnician();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Label', 'patrimonio' => 'PAT-001', 'status' => Asset::STATUS_DISPONIVEL]);
        $plano = MaintenancePlan::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Revisão preventiva 250h', 'interval_hours' => 250]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_plan_id' => $plano->id,
            'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);
        $allocation = TechnicianAllocation::create([
            'tenant_id' => $tenant->id, 'technician_id' => $technician->id, 'maintenance_order_id' => $order->id,
            'starts_at' => now(), 'ends_at' => now()->addHours(2),
        ]);

        $this->assertSame('PAT-001 · Revisão preventiva 250h', $allocation->displayLabel());
    }

    public function test_label_for_corrective_order_shows_pat_and_failure_category(): void
    {
        [$tenant, $technician] = $this->makeTenantAndTechnician();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Corretiva', 'patrimonio' => 'PAT-002', 'status' => Asset::STATUS_DISPONIVEL]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'failure_category' => MaintenanceOrder::FAILURE_CATEGORY_ELETRICO,
            'internal_status' => 'aguardando_diagnostico',
        ]);
        $allocation = TechnicianAllocation::create([
            'tenant_id' => $tenant->id, 'technician_id' => $technician->id, 'maintenance_order_id' => $order->id,
            'starts_at' => now(), 'ends_at' => now()->addHours(2),
        ]);

        $this->assertSame('PAT-002 · Elétrico', $allocation->displayLabel());
    }

    public function test_label_for_due_alert_without_order_shows_pat_and_plan_name(): void
    {
        [$tenant, $technician] = $this->makeTenantAndTechnician();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Alerta', 'patrimonio' => 'PAT-003', 'status' => Asset::STATUS_DISPONIVEL, 'horimetro_atual' => 500]);
        $plano = MaintenancePlan::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Troca de óleo', 'interval_hours' => 250]);
        $alert = MaintenanceDueAlert::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_plan_id' => $plano->id, 'alerted_at' => now()]);
        $allocation = TechnicianAllocation::create([
            'tenant_id' => $tenant->id, 'technician_id' => $technician->id, 'maintenance_due_alert_id' => $alert->id,
            'starts_at' => now(), 'ends_at' => now()->addHours(2),
        ]);

        $this->assertSame('PAT-003 · Troca de óleo', $allocation->displayLabel());
    }

    public function test_label_falls_back_to_generic_when_neither_order_nor_alert_exists(): void
    {
        [$tenant, $technician] = $this->makeTenantAndTechnician();
        $allocation = TechnicianAllocation::create([
            'tenant_id' => $tenant->id, 'technician_id' => $technician->id,
            'starts_at' => now(), 'ends_at' => now()->addHours(2),
        ]);

        $this->assertSame('Alocação', $allocation->displayLabel());
    }
}
