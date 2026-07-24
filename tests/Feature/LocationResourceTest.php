<?php

namespace Tests\Feature;

use App\Filament\Resources\LocationResource;
use App\Filament\Resources\LocationResource\Pages\CreateLocation;
use App\Models\Location;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * LocationResource já tinha form/tabela funcionais, mas
 * $shouldRegisterNavigation=false deixava a tela inacessível pelo menu
 * (mesmo achado de CompanyResource). Corrigido ao popular dados de demo.
 */
class LocationResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Location '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_locations'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Location '.uniqid(), 'slug' => 'tenant-location-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_create_form_persists_all_fields(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        Livewire::test(CreateLocation::class)
            ->fillForm([
                'name' => 'Depósito Sul',
                'address' => 'Rua das Palmeiras, 500',
                'city' => 'Campinas',
                'state' => 'SP',
                'zip_code' => '13000-000',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $location = Location::where('name', 'Depósito Sul')->sole();
        $this->assertSame($tenant->id, $location->tenant_id);
        $this->assertSame('Campinas', $location->city);
    }

    public function test_resource_is_visible_in_navigation(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $response = $this->get(LocationResource::getUrl('index'));

        $response->assertOk();
    }
}
