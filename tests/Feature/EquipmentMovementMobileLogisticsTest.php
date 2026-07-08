<?php

namespace Tests\Feature;

use App\Livewire\EquipmentMovementMobile;
use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Atribuicao de veiculo/motorista e checkpoints de rastreamento dentro do
 * checklist mobile de mobilizacao/desmobilizacao (modulo de Logistica,
 * 2026-07-11).
 */
class EquipmentMovementMobileLogisticsTest extends TestCase
{
    use RefreshDatabase;

    private function makeScenario(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Mobile Logistica '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_equipment_movements', 'tabela_fleet_vehicles', 'tabela_fleet_drivers'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Mobile Logistica '.uniqid(), 'slug' => 'tenant-mobile-log-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Mobile', 'status' => Asset::STATUS_DISPONIVEL]);
        $maintenanceOrder = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Mobilização de teste', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        return [$tenant, $admin, $asset, $maintenanceOrder];
    }

    public function test_only_available_vehicles_appear_in_the_assignment_dropdown(): void
    {
        [$tenant, $admin, , $maintenanceOrder] = $this->makeScenario();
        $disponivel = FleetVehicle::create(['tenant_id' => $tenant->id, 'placa' => 'MOB1111', 'modelo' => 'Truck', 'tipo' => 'truck']);
        $emManutencao = FleetVehicle::create([
            'tenant_id' => $tenant->id, 'placa' => 'MOB2222', 'modelo' => 'Truck', 'tipo' => 'truck',
            'status' => FleetVehicle::STATUS_MANUTENCAO,
        ]);

        $this->actingAs($admin);

        $available = Livewire::test(EquipmentMovementMobile::class, [
            'maintenanceOrder' => $maintenanceOrder,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
        ])->instance()->availableVehicles;

        $this->assertTrue($available->contains('id', $disponivel->id));
        $this->assertFalse($available->contains('id', $emManutencao->id));
    }

    public function test_assigning_a_vehicle_persists_and_marks_it_em_rota(): void
    {
        [$tenant, $admin, , $maintenanceOrder] = $this->makeScenario();
        $vehicle = FleetVehicle::create(['tenant_id' => $tenant->id, 'placa' => 'MOB3333', 'modelo' => 'Truck', 'tipo' => 'truck']);
        $driver = FleetDriver::create(['tenant_id' => $tenant->id, 'name' => 'Motorista Mobile', 'active' => true]);

        $this->actingAs($admin);

        $component = Livewire::test(EquipmentMovementMobile::class, [
            'maintenanceOrder' => $maintenanceOrder,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
        ]);

        $component->set('fleetVehicleId', $vehicle->id)
            ->set('fleetDriverId', $driver->id);

        $movement = $component->instance()->equipmentMovement->fresh();

        $this->assertSame($vehicle->id, $movement->fleet_vehicle_id);
        $this->assertSame($driver->id, $movement->fleet_driver_id);
        $this->assertSame(FleetVehicle::STATUS_EM_ROTA, $vehicle->fresh()->status);
        $this->assertSame(EquipmentMovement::STATUS_EM_ANDAMENTO, $movement->status, 'atribuir veiculo deveria contar como "comecou" a movimentacao');
    }

    public function test_registering_a_checkpoint_creates_a_location_and_counts_as_started(): void
    {
        [$tenant, $admin, , $maintenanceOrder] = $this->makeScenario();

        $this->actingAs($admin);

        $component = Livewire::test(EquipmentMovementMobile::class, [
            'maintenanceOrder' => $maintenanceOrder,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
        ]);

        $component->call('registrarCheckpoint', 'saida_patio', -22.9056, -47.0608);

        $movement = $component->instance()->equipmentMovement->fresh();

        $this->assertCount(1, $movement->locations);
        $this->assertSame('saida_patio', $movement->locations->first()->checkpoint_type);
        $this->assertSame($admin->id, $movement->locations->first()->captured_by_user_id);
        $this->assertSame(EquipmentMovement::STATUS_EM_ANDAMENTO, $movement->status);
    }

    public function test_registering_a_checkpoint_rejects_an_invalid_type(): void
    {
        [$tenant, $admin, , $maintenanceOrder] = $this->makeScenario();
        $this->actingAs($admin);

        $component = Livewire::test(EquipmentMovementMobile::class, [
            'maintenanceOrder' => $maintenanceOrder,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
        ]);

        $component->call('registrarCheckpoint', 'tipo_invalido', -22.9056, -47.0608)
            ->assertHasErrors(['checkpoint_type']);
    }
}
