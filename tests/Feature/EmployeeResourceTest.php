<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\EmployeeResource\Pages\CreateEmployee;
use App\Filament\Resources\EquipmentAllocationResource;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_user_id_select_only_offers_users_from_the_current_tenant(): void
    {
        $plan = Plan::create([
            'name' => 'Plano employee select', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_employees', 'tabela_equipment_allocations'],
        ]);

        $tenantA = Tenant::create([
            'name' => 'Tenant A select', 'slug' => 'tenant-a-select-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'features' => null,
        ]);
        $tenantB = Tenant::create([
            'name' => 'Tenant B select', 'slug' => 'tenant-b-select-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'features' => null,
        ]);

        $roleA = Role::create(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenantA->id]);
        $adminA = User::create([
            'name' => 'Admin Tenant A', 'email' => 'admin-a-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('senha12345'), 'tenant_id' => $tenantA->id, 'role' => 'admin',
        ]);
        $adminA->forceFill(['email_verified_at' => now()])->save();
        $adminA->assignRole($roleA);

        $userB = User::create([
            'name' => 'Usuário do Tenant B', 'email' => 'user-b-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('senha12345'), 'tenant_id' => $tenantB->id, 'role' => 'colaborador',
        ]);

        $this->actingAs($adminA);

        // Bug encontrado 2026-08-18: o Select::relationship('user', 'name')
        // do EmployeeResource::form() (campo "Usuário do painel vinculado")
        // não tinha modifyQueryUsing, vazando todos os usuários de todos os
        // tenants no dropdown de /admin/employees/create. Exercita o
        // componente Select de verdade (getSelectOptions), não uma
        // reimplementação da query.
        $component = Livewire::test(CreateEmployee::class);

        $field = $component->instance()
            ->form
            ->getFlatFields()['user_id'];

        $options = $field->getSearchResults('');

        $this->assertContains($adminA->name, $options);
        $this->assertNotContains($userB->name, $options);
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
