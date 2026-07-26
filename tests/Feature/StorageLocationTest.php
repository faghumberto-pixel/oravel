<?php

namespace Tests\Feature;

use App\Filament\Resources\StorageLocationResource\Pages\CreateStorageLocation;
use App\Models\InternalUnit;
use App\Models\Plan;
use App\Models\Role;
use App\Models\StorageLocation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StorageLocationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Storage '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_storage_locations', 'tabela_internal_units'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Storage '.uniqid(), 'slug' => 'tenant-storage-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now(), 'is_approved' => true])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_creating_a_storage_location_via_resource_form(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $unit = InternalUnit::create(['tenant_id' => $tenant->id, 'name' => 'Matriz', 'code' => 'MATRIZ', 'type' => 'matriz']);

        Livewire::test(CreateStorageLocation::class)
            ->fillForm([
                'internal_unit_id' => $unit->id,
                'context' => StorageLocation::CONTEXT_ALMOXARIFADO,
                'code' => 'A1-01',
                'row' => 1,
                'column' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('storage_locations', ['tenant_id' => $tenant->id, 'code' => 'A1-01']);
    }

    public function test_a_tenant_never_sees_another_tenants_storage_locations(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        [$tenantB] = $this->makeTenantAdmin();

        $unitB = InternalUnit::create(['tenant_id' => $tenantB->id, 'name' => 'Matriz B', 'code' => 'B', 'type' => 'matriz']);
        StorageLocation::create([
            'tenant_id' => $tenantB->id, 'internal_unit_id' => $unitB->id,
            'context' => StorageLocation::CONTEXT_ALMOXARIFADO, 'code' => 'B-01', 'row' => 1, 'column' => 1,
        ]);

        $this->actingAs($adminA);

        $this->assertSame(0, StorageLocation::count());
    }

    public function test_grid_position_is_unique_per_unit_and_context(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        $unit = InternalUnit::create(['tenant_id' => $tenant->id, 'name' => 'Matriz', 'code' => 'MATRIZ', 'type' => 'matriz']);

        StorageLocation::create([
            'tenant_id' => $tenant->id, 'internal_unit_id' => $unit->id,
            'context' => StorageLocation::CONTEXT_ALMOXARIFADO, 'code' => 'A1-01', 'row' => 1, 'column' => 1,
        ]);

        $this->expectException(QueryException::class);

        StorageLocation::create([
            'tenant_id' => $tenant->id, 'internal_unit_id' => $unit->id,
            'context' => StorageLocation::CONTEXT_ALMOXARIFADO, 'code' => 'A1-02', 'row' => 1, 'column' => 1,
        ]);
    }
}
