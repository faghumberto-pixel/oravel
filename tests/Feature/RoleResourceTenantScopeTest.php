<?php

namespace Tests\Feature;

use App\Filament\Resources\RoleResource;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regressao: RoleResource::getEloquentQuery() ignorava o escopo de tenant
 * inteiramente pra super admin ("Super admin ve todos"), mesmo depois de
 * escolher um tenant atuante -- como a maioria dos tenants usa os mesmos
 * nomes de papel (Comercial, tecnico, admin...), a lista misturava todos os
 * tenants e mostrava o mesmo nome repetido varias vezes. Corrigido pra usar
 * Tenancy::current() (mesma fonte usada em todo o resto do painel pro
 * "tenant atuante"), tanto na leitura quanto na criacao (CreateRole).
 */
class RoleResourceTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(string $name): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano '.$name.' '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_roles'],
        ]);

        return Tenant::create([
            'name' => $name, 'slug' => str($name)->slug().'-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    /**
     * firstOrCreate() (nao Role::create() direto): o Spatie valida nome+guard
     * unicos GLOBALMENTE no create() estatico dele (teams mode desligado,
     * nao sabe de tenant_id) -- so nao bloqueia porque firstOrCreate() passa
     * pelo builder/save(), nao pelo create() customizado. Mesmo padrao ja
     * usado em todo o resto do app (TenantProvisioner, RealisticDemoSeeder).
     */
    private function makeRole(Tenant $tenant, string $name): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
    }

    /**
     * Email fixo, nao aleatorio: User::isSuperAdmin() checa contra a
     * allowlist de config('oravel.super_admins') (SUPER_ADMINS no .env),
     * nao qualquer email @oravel.com.br -- so esse endereco especifico
     * conta como super admin de verdade (mesmo usado em
     * SuperAdminActingTenantTest).
     */
    private function makeSuperAdmin(): User
    {
        return User::create([
            'name' => 'Super', 'email' => 'humberto@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null, 'email_verified_at' => now(),
        ]);
    }

    public function test_super_admin_without_acting_tenant_sees_no_roles(): void
    {
        $tenantA = $this->makeTenant('Tenant A');
        $this->makeRole($tenantA, 'Comercial');

        $this->actingAs($this->makeSuperAdmin());

        $this->assertSame(0, RoleResource::getEloquentQuery()->count());
    }

    public function test_super_admin_with_acting_tenant_sees_only_that_tenants_roles_not_mixed_with_others(): void
    {
        $tenantA = $this->makeTenant('Tenant A');
        $tenantB = $this->makeTenant('Tenant B');
        $this->makeRole($tenantA, 'Comercial');
        $this->makeRole($tenantA, 'tecnico');
        $this->makeRole($tenantB, 'Comercial');

        $this->actingAs($this->makeSuperAdmin());
        session(['acting_tenant_id' => $tenantA->id]);

        $names = RoleResource::getEloquentQuery()->pluck('name');

        $this->assertCount(2, $names);
        $this->assertTrue($names->contains('Comercial'));
        $this->assertTrue($names->contains('tecnico'));
    }

    public function test_creating_a_role_as_super_admin_stamps_the_acting_tenant_id(): void
    {
        $tenant = $this->makeTenant('Tenant Criacao');
        $this->actingAs($this->makeSuperAdmin());
        session(['acting_tenant_id' => $tenant->id]);

        $method = new \ReflectionMethod(CreateRole::class, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);
        $page = (new \ReflectionClass(CreateRole::class))->newInstanceWithoutConstructor();

        $data = $method->invoke($page, ['name' => 'Papel Novo']);

        $this->assertEquals($tenant->id, $data['tenant_id'] ?? null);
    }

    public function test_creating_a_role_without_acting_tenant_does_not_stamp_any_tenant(): void
    {
        $this->actingAs($this->makeSuperAdmin());

        $method = new \ReflectionMethod(CreateRole::class, 'mutateFormDataBeforeCreate');
        $method->setAccessible(true);
        $page = (new \ReflectionClass(CreateRole::class))->newInstanceWithoutConstructor();

        $data = $method->invoke($page, ['name' => 'Papel Novo']);

        $this->assertArrayNotHasKey('tenant_id', $data);
    }
}
