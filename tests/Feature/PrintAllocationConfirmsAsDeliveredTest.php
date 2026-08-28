<?php

namespace Tests\Feature;

use App\Filament\Pages\AlocacaoTecnicosPmp;
use App\Models\Asset;
use App\Models\MaintenanceDueAlert;
use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Role;
use App\Models\TechnicianAllocation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Nem todo técnico usa o app -- "Imprimir OS" no Gantt marca a alocação
 * como entregue de uma vez (delivery_mode=impressa + status=confirmado),
 * sem passo de aceite digital nesse modo.
 */
class PrintAllocationConfirmsAsDeliveredTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Print Allocation '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Print Allocation '.uniqid(), 'slug' => 'tenant-print-allocation-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin Print Allocation', 'email' => 'admin-print-allocation-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_printing_allocation_marks_it_as_printed_and_confirmed(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Print', 'status' => Asset::STATUS_DISPONIVEL]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Corretiva print',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);
        $technician = User::create([
            'name' => 'Tecnico Print', 'email' => 'tecnico-print-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $allocation = TechnicianAllocation::create([
            'tenant_id' => $tenant->id, 'technician_id' => $technician->id, 'maintenance_order_id' => $order->id,
            'starts_at' => now(), 'ends_at' => now()->addHours(2),
        ]);

        $this->assertSame(TechnicianAllocation::DELIVERY_DIGITAL, $allocation->delivery_mode);
        $this->assertSame(TechnicianAllocation::STATUS_PLANEJADO, $allocation->status);

        $this->actingAs($admin);

        Livewire::test(AlocacaoTecnicosPmp::class)
            ->call('printAllocation', $allocation->id);

        $allocation->refresh();
        $this->assertSame(TechnicianAllocation::DELIVERY_IMPRESSA, $allocation->delivery_mode);
        $this->assertSame(TechnicianAllocation::STATUS_CONFIRMADO, $allocation->status);
    }

    public function test_printing_allocation_without_order_does_nothing(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Sem OS', 'status' => Asset::STATUS_DISPONIVEL, 'horimetro_atual' => 500,
        ]);
        $plano = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'name' => 'Plano Sem OS',
            'interval_hours' => 250, 'last_service_hours' => 0,
        ]);
        $alert = MaintenanceDueAlert::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_plan_id' => $plano->id,
            'alerted_at' => now(),
        ]);
        $technician = User::create([
            'name' => 'Tecnico Sem OS', 'email' => 'tecnico-sem-os-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $allocation = TechnicianAllocation::create([
            'tenant_id' => $tenant->id, 'technician_id' => $technician->id, 'maintenance_due_alert_id' => $alert->id,
            'starts_at' => now(), 'ends_at' => now()->addHours(2),
        ]);

        $this->actingAs($admin);

        Livewire::test(AlocacaoTecnicosPmp::class)
            ->call('printAllocation', $allocation->id);

        $allocation->refresh();
        $this->assertSame(TechnicianAllocation::DELIVERY_DIGITAL, $allocation->delivery_mode);
        $this->assertSame(TechnicianAllocation::STATUS_PLANEJADO, $allocation->status);
    }
}
