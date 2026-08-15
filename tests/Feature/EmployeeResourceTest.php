<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\EquipmentAllocationResource;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_admin_has_full_access_to_employee_and_allocation_resources(): void
    {
        $plan = Plan::create([
            'name' => 'Plano employee resource', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_employees', 'tabela_equipment_allocations'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant employee resource', 'slug' => 'tenant-employee-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'features' => null,
        ]);

        $role = Role::create(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $user = User::create([
            'name' => 'Admin Employee Test', 'email' => 'admin-emp-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('senha12345'), 'tenant_id' => $tenant->id, 'role' => 'admin',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole($role);

        $this->actingAs($user);

        $this->assertTrue(EmployeeResource::canViewAny());
        $this->assertTrue(EmployeeResource::canCreate());
        $this->assertTrue(EquipmentAllocationResource::canViewAny());
        $this->assertTrue(EquipmentAllocationResource::canCreate());
    }

    public function test_access_is_denied_without_the_plan_feature(): void
    {
        $plan = Plan::create([
            'name' => 'Plano sem employees', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant sem employees', 'slug' => 'tenant-sem-employees-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'features' => null,
        ]);

        $role = Role::create(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $user = User::create([
            'name' => 'Admin Sem Feature', 'email' => 'admin-sem-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('senha12345'), 'tenant_id' => $tenant->id, 'role' => 'admin',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole($role);

        $this->actingAs($user);

        // Trava comercial soberana: sem a feature no plano, nem admin do
        // tenant enxerga o módulo -- ver AbstractPolicy::check().
        $this->assertFalse(EmployeeResource::canViewAny());
    }
}
