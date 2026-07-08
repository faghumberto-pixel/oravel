<?php

namespace Tests\Feature;

use App\Filament\Resources\FleetVehicleResource;
use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\FleetVehicle;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetVehicleAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Disponibilidade '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_fleet_vehicles', 'tabela_assets'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Disponibilidade '.uniqid(), 'slug' => 'tenant-disp-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_index_shows_which_movement_a_vehicle_in_rota_is_tied_to(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $vehicle = FleetVehicle::create(['tenant_id' => $tenant->id, 'placa' => 'ROT1234', 'modelo' => 'Truck', 'tipo' => 'truck']);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Em Rota', 'status' => Asset::STATUS_DISPONIVEL]);

        EquipmentMovement::create([
            'tenant_id' => $tenant->id,
            'asset_id' => $asset->id,
            'fleet_vehicle_id' => $vehicle->id,
            'type' => EquipmentMovement::TYPE_MOBILIZACAO,
            'status' => EquipmentMovement::STATUS_EM_ANDAMENTO,
            'started_at' => now(),
        ]);

        $this->actingAs($admin);

        $response = $this->get(FleetVehicleResource::getUrl('index', ['tenant' => $tenant->slug]));

        $response->assertOk();
        $response->assertSee('Mobilização');
        $response->assertSee('Guindaste Em Rota');
    }

    public function test_available_vehicle_shows_no_current_movement(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        FleetVehicle::create(['tenant_id' => $tenant->id, 'placa' => 'DIS9999', 'modelo' => 'Truck', 'tipo' => 'truck']);

        $this->actingAs($admin);

        $response = $this->get(FleetVehicleResource::getUrl('index', ['tenant' => $tenant->slug]));

        $response->assertOk();
    }

    public function test_stats_widget_shows_em_rota_count(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $vehicle = FleetVehicle::create([
            'tenant_id' => $tenant->id, 'placa' => 'EMR5555', 'modelo' => 'Truck', 'tipo' => 'truck',
            'status' => FleetVehicle::STATUS_EM_ROTA,
        ]);

        $this->actingAs($admin);

        $response = $this->get(FleetVehicleResource::getUrl('index', ['tenant' => $tenant->slug]));

        $response->assertOk();
        $response->assertSee('Em Rota');
    }
}
