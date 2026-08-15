<?php

namespace Tests\Feature;

use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ata 2026-08-15: tecnico/motorista precisam ser User (login, atualiza OS)
 * e Employee (RH, bate ponto via TimeClock.employee_id) ao mesmo tempo --
 * antes disso os dois cadastros eram independentes e nada forcava o vinculo.
 * Este teste cobre o formulario unificado em UserResource.
 */
class UserResourceEmployeeSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // tenant_user nao tem migration no schema atual (achado pre-existente,
        // ver memoria project_tenant_user_pivot_table_missing) mas
        // CreateUser::afterCreate() sempre tenta gravar nela -- criamos aqui
        // soh pra nao deixar esse bug nao-relacionado quebrar os testes desta
        // classe, que sao sobre o vinculo User<->Employee.
        if (! Schema::hasTable('tenant_user')) {
            Schema::create('tenant_user', function (Blueprint $table) {
                $table->foreignUuid('tenant_id');
                $table->foreignUuid('user_id');
                $table->primary(['tenant_id', 'user_id']);
            });
        }
    }

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano User Employee '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_employees', 'tabela_users'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant User Employee '.uniqid(), 'slug' => 'tenant-user-employee-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        $department = Department::create(['tenant_id' => $tenant->id, 'name' => 'Operações']);
        $operationalRole = Role::create(['name' => 'tecnico', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

        return [$tenant, $admin, $department, $operationalRole];
    }

    public function test_creating_a_user_with_cpf_creates_a_linked_employee(): void
    {
        [$tenant, $admin, $department, $operationalRole] = $this->makeTenantAdmin();
        $this->actingAs($admin);
        $this->get(UserResource::getUrl('create', ['tenant' => $tenant->slug]))->assertOk();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'department_id' => $department->id,
                'roles' => [$operationalRole->id],
                'name' => 'Tecnico Novo',
                'email' => 'tecnico-'.uniqid().'@oravel.com.br',
                'hourly_rate' => 10,
                'password' => 'senha12345',
                'is_approved' => true,
                'employee_cpf' => '12345678901',
                'employee_role_title' => 'Tecnico de Campo',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('name', 'Tecnico Novo')->firstOrFail();

        $employee = Employee::where('user_id', $user->id)->first();
        $this->assertNotNull($employee, 'Employee deveria ter sido criado junto com o User.');
        $this->assertSame('12345678901', $employee->cpf);
        $this->assertSame('Tecnico de Campo', $employee->role_title);
        $this->assertSame($tenant->id, $employee->tenant_id);
    }

    public function test_creating_a_user_without_cpf_does_not_create_an_employee(): void
    {
        [$tenant, $admin, $department, $operationalRole] = $this->makeTenantAdmin();
        $this->actingAs($admin);
        $this->get(UserResource::getUrl('create', ['tenant' => $tenant->slug]))->assertOk();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'department_id' => $department->id,
                'roles' => [$operationalRole->id],
                'name' => 'Usuario Sem RH',
                'email' => 'semrh-'.uniqid().'@oravel.com.br',
                'hourly_rate' => 10,
                'password' => 'senha12345',
                'is_approved' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('name', 'Usuario Sem RH')->firstOrFail();

        $this->assertNull(Employee::where('user_id', $user->id)->first());
    }

    public function test_editing_a_user_to_add_cpf_creates_the_employee(): void
    {
        [$tenant, $admin, $department, $operationalRole] = $this->makeTenantAdmin();
        $user = User::create([
            'name' => 'Tecnico Existente', 'email' => 'existente-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
            'department_id' => $department->id,
        ]);
        $user->assignRole($operationalRole);
        $this->actingAs($admin);
        $this->get(UserResource::getUrl('edit', ['tenant' => $tenant->slug, 'record' => $user]))->assertOk();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm([
                'employee_cpf' => '98765432100',
                'employee_role_title' => 'Motorista',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $employee = Employee::where('user_id', $user->id)->first();
        $this->assertNotNull($employee);
        $this->assertSame('98765432100', $employee->cpf);
        $this->assertSame('Motorista', $employee->role_title);
    }

    public function test_duplicate_cpf_in_the_same_tenant_is_rejected(): void
    {
        [$tenant, $admin, $department, $operationalRole] = $this->makeTenantAdmin();
        $existingUser = User::create([
            'name' => 'Ja Tem Employee', 'email' => 'jatem-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        Employee::create([
            'tenant_id' => $tenant->id, 'user_id' => $existingUser->id,
            'name' => $existingUser->name, 'cpf' => '11122233344', 'status' => Employee::STATUS_ATIVO,
        ]);

        $this->actingAs($admin);
        $this->get(UserResource::getUrl('create', ['tenant' => $tenant->slug]))->assertOk();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'department_id' => $department->id,
                'roles' => [$operationalRole->id],
                'name' => 'Cpf Duplicado',
                'email' => 'dup-'.uniqid().'@oravel.com.br',
                'hourly_rate' => 10,
                'password' => 'senha12345',
                'is_approved' => true,
                'employee_cpf' => '11122233344',
            ])
            ->call('create')
            ->assertHasFormErrors(['employee_cpf']);

        $this->assertNull(User::where('name', 'Cpf Duplicado')->first());
    }
}
