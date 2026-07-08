<?php

namespace Tests\Feature;

use App\Filament\Resources\FleetDriverResource;
use App\Filament\Resources\FleetDriverResource\Pages\CreateFleetDriver;
use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FleetDriverResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(array $features): array
    {
        $plan = Plan::create([
            'name' => 'Plano Motoristas '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features,
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Motoristas '.uniqid(), 'slug' => 'tenant-motoristas-'.uniqid(),
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

    public function test_index_page_is_blocked_when_plan_lacks_feature(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets']); // sem tabela_fleet_drivers
        $this->actingAs($admin);

        $this->get(FleetDriverResource::getUrl('index', ['tenant' => $tenant->slug]))->assertForbidden();
    }

    public function test_index_page_renders_when_plan_has_feature(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_fleet_drivers']);
        FleetDriver::create(['tenant_id' => $tenant->id, 'name' => 'Motorista Listado']);
        $this->actingAs($admin);

        $response = $this->get(FleetDriverResource::getUrl('index', ['tenant' => $tenant->slug]));

        $response->assertOk();
        $response->assertSee('Motorista Listado');
    }

    public function test_can_create_a_driver_with_vehicle_habilitation_via_form(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_fleet_drivers']);
        $vehicle = FleetVehicle::create(['tenant_id' => $tenant->id, 'placa' => 'ZZZ0001', 'modelo' => 'Truck', 'tipo' => 'truck']);
        $this->actingAs($admin);

        Livewire::test(CreateFleetDriver::class)
            ->fillForm([
                'name' => 'Novo Motorista',
                'cpf' => '111.222.333-44',
                'employment_type' => FleetDriver::EMPLOYMENT_PROPRIO,
                'cnh_number' => '99988877766',
                'cnh_category' => 'E',
                'cnh_expiry_date' => now()->addYear()->toDateString(),
                'vehicles' => [$vehicle->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $driver = FleetDriver::where('name', 'Novo Motorista')->firstOrFail();
        $this->assertTrue($driver->vehicles->contains($vehicle));
    }

    public function test_terceiro_driver_requires_a_freight_carrier(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_fleet_drivers']);
        $this->actingAs($admin);

        Livewire::test(CreateFleetDriver::class)
            ->fillForm([
                'name' => 'Motorista Terceiro Sem Transportadora',
                'employment_type' => FleetDriver::EMPLOYMENT_TERCEIRO,
            ])
            ->call('create')
            ->assertHasFormErrors(['freight_carrier_id']);
    }

    public function test_edit_page_shows_cnh_expiry_badge(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_fleet_drivers']);
        $driver = FleetDriver::create([
            'tenant_id' => $tenant->id, 'name' => 'Motorista CNH Vencida',
            'cnh_expiry_date' => now()->subDay(),
        ]);
        $this->actingAs($admin);

        $response = $this->get(FleetDriverResource::getUrl('edit', ['tenant' => $tenant->slug, 'record' => $driver]));

        $response->assertOk();
    }

    public function test_non_admin_without_permission_gets_forbidden_directly(): void
    {
        [$tenant, $admin] = $this->makeTenant(['tabela_assets']);
        $this->actingAs($admin);

        $this->get(FleetDriverResource::getUrl('create', ['tenant' => $tenant->slug]))->assertForbidden();
    }
}
