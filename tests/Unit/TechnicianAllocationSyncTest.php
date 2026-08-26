<?php

namespace Tests\Unit;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\TechnicianAllocation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TechnicianAllocation e' a fonte de verdade pro Gantt de Alocacao de
 * Tecnicos, mas MaintenanceOrder.technician_id/scheduled_at continuam
 * sendo lidos por PainelPmp/AgendaTecnicoWidget/CargaTecnica sem
 * alteracao -- o Observer precisa manter os dois sincronizados.
 */
class TechnicianAllocationSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAssetOrderAndTechnician(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Alocacao '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Alocacao '.uniqid(), 'slug' => 'tenant-alocacao-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $technician = User::create([
            'name' => 'Tecnico Alocacao', 'email' => 'tecnico-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Alocacao', 'status' => Asset::STATUS_DISPONIVEL]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'description' => 'Preventiva a alocar', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
            'internal_status' => 'aguardando_diagnostico',
        ]);

        return [$tenant, $order, $technician];
    }

    public function test_creating_allocation_syncs_technician_and_scheduled_at_onto_order(): void
    {
        [$tenant, $order, $technician] = $this->makeTenantAssetOrderAndTechnician();

        $startsAt = now()->addDay()->setTime(9, 0);

        TechnicianAllocation::create([
            'tenant_id' => $tenant->id,
            'technician_id' => $technician->id,
            'maintenance_order_id' => $order->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
        ]);

        $order->refresh();

        $this->assertSame($technician->id, $order->technician_id);
        $this->assertTrue($order->scheduled_at->equalTo($startsAt));
    }

    public function test_allocation_without_maintenance_order_does_not_touch_any_order(): void
    {
        [$tenant, $order, $technician] = $this->makeTenantAssetOrderAndTechnician();

        TechnicianAllocation::create([
            'tenant_id' => $tenant->id,
            'technician_id' => $technician->id,
            'maintenance_order_id' => null,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
        ]);

        $order->refresh();

        $this->assertNull($order->technician_id);
    }

    public function test_updating_allocation_technician_resyncs_order(): void
    {
        [$tenant, $order, $technician] = $this->makeTenantAssetOrderAndTechnician();

        $otherTechnician = User::create([
            'name' => 'Outro Tecnico', 'email' => 'outro-tecnico-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        $allocation = TechnicianAllocation::create([
            'tenant_id' => $tenant->id,
            'technician_id' => $technician->id,
            'maintenance_order_id' => $order->id,
            'starts_at' => now(),
            'ends_at' => now()->addHour(),
        ]);

        $allocation->update(['technician_id' => $otherTechnician->id]);

        $order->refresh();
        $this->assertSame($otherTechnician->id, $order->technician_id);
    }
}
