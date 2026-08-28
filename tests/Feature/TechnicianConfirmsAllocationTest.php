<?php

namespace Tests\Feature;

use App\Filament\Pages\TechnicianDailyTasks;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\TechnicianAllocation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido do usuário 2026-08-28: alocação digital nasce pendente
 * (STATUS_PLANEJADO) e só conta como aceita quando o técnico confirma na
 * própria tela mobile que já usa ("Minhas Ordens de Serviço") -- sem
 * página nova e isolada no admin.
 */
class TechnicianConfirmsAllocationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAndTechnician(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Confirm '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Confirm '.uniqid(), 'slug' => 'tenant-confirm-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $technician = User::create([
            'name' => 'Tecnico Confirm', 'email' => 'tecnico-confirm-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $technician->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();

        return [$tenant, $technician];
    }

    private function makeAllocation(Tenant $tenant, User $technician, string $deliveryMode = TechnicianAllocation::DELIVERY_DIGITAL): TechnicianAllocation
    {
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Confirm', 'patrimonio' => 'PAT-CONFIRM', 'status' => Asset::STATUS_DISPONIVEL]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'Corretiva confirm',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'aguardando_diagnostico',
        ]);

        return TechnicianAllocation::create([
            'tenant_id' => $tenant->id, 'technician_id' => $technician->id, 'maintenance_order_id' => $order->id,
            'starts_at' => now(), 'ends_at' => now()->addHours(2), 'delivery_mode' => $deliveryMode,
        ]);
    }

    public function test_technician_sees_own_pending_digital_allocation(): void
    {
        [$tenant, $technician] = $this->makeTenantAndTechnician();
        $allocation = $this->makeAllocation($tenant, $technician);

        $this->actingAs($technician);

        $pending = Livewire::test(TechnicianDailyTasks::class)->get('pendingAllocations');

        $this->assertCount(1, $pending);
        $this->assertSame($allocation->id, $pending->first()->id);
    }

    public function test_printed_allocation_does_not_appear_as_pending_digital(): void
    {
        [$tenant, $technician] = $this->makeTenantAndTechnician();
        $this->makeAllocation($tenant, $technician, TechnicianAllocation::DELIVERY_IMPRESSA);

        $this->actingAs($technician);

        $pending = Livewire::test(TechnicianDailyTasks::class)->get('pendingAllocations');

        $this->assertCount(0, $pending);
    }

    public function test_technician_does_not_see_other_technicians_allocation(): void
    {
        [$tenant, $technician] = $this->makeTenantAndTechnician();
        $otherTechnician = User::create([
            'name' => 'Outro Tecnico', 'email' => 'outro-tecnico-confirm-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $otherTechnician->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();

        $this->makeAllocation($tenant, $otherTechnician);

        $this->actingAs($technician);

        $pending = Livewire::test(TechnicianDailyTasks::class)->get('pendingAllocations');

        $this->assertCount(0, $pending);
    }

    public function test_confirm_allocation_changes_status(): void
    {
        [$tenant, $technician] = $this->makeTenantAndTechnician();
        $allocation = $this->makeAllocation($tenant, $technician);

        $this->actingAs($technician);

        Livewire::test(TechnicianDailyTasks::class)
            ->call('confirmAllocation', $allocation->id);

        $allocation->refresh();
        $this->assertSame(TechnicianAllocation::STATUS_CONFIRMADO, $allocation->status);
    }

    public function test_technician_cannot_confirm_another_technicians_allocation(): void
    {
        [$tenant, $technician] = $this->makeTenantAndTechnician();
        $otherTechnician = User::create([
            'name' => 'Outro Tecnico Confirm', 'email' => 'outro-tecnico-confirm2-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $otherTechnician->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();

        $allocation = $this->makeAllocation($tenant, $otherTechnician);

        $this->actingAs($technician);

        Livewire::test(TechnicianDailyTasks::class)
            ->call('confirmAllocation', $allocation->id);

        $allocation->refresh();
        $this->assertSame(TechnicianAllocation::STATUS_PLANEJADO, $allocation->status);
    }
}
