<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TenantFeatureOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(Tenant $tenant): User
    {
        $user = User::create([
            'name' => 'Verify Admin',
            'email' => 'verify-admin-' . uniqid() . '@oravel.com.br',
            'password' => bcrypt('teste123'),
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'], ['tenant_id' => $tenant->id]);
        $user->assignRole($role);

        return $user;
    }

    public function test_tenant_admin_denied_when_plan_lacks_feature_and_no_override(): void
    {
        $plan = Plan::create([
            'name' => 'Plano sem clientes', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant sem override', 'slug' => 'sem-override-' . uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'features' => null,
        ]);
        $user = $this->makeTenantAdmin($tenant);

        $this->actingAs($user);
        $this->assertFalse(ClientResource::canViewAny());
    }

    public function test_tenant_override_grants_feature_plan_does_not_include(): void
    {
        $plan = Plan::create([
            'name' => 'Plano sem clientes 2', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant com override', 'slug' => 'com-override-' . uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'features' => ['tabela_clients'],
        ]);
        $user = $this->makeTenantAdmin($tenant);

        $this->actingAs($user);
        $this->assertTrue(ClientResource::canViewAny());
    }

    public function test_plan_granted_feature_still_works_without_any_tenant_override(): void
    {
        $plan = Plan::create([
            'name' => 'Plano com clientes', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant plano direto', 'slug' => 'plano-direto-' . uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'features' => null,
        ]);
        $user = $this->makeTenantAdmin($tenant);

        $this->actingAs($user);
        $this->assertTrue(ClientResource::canViewAny());
    }

    public function test_neither_plan_nor_override_still_denies(): void
    {
        $plan = Plan::create([
            'name' => 'Plano vazio', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant vazio', 'slug' => 'vazio-' . uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'features' => ['tabela_assets'],
        ]);
        $user = $this->makeTenantAdmin($tenant);

        $this->actingAs($user);
        $this->assertFalse(ClientResource::canViewAny());
    }

    public function test_super_admin_bypasses_feature_gate_entirely(): void
    {
        $plan = Plan::create([
            'name' => 'Plano vazio 2', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => [],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant super admin', 'slug' => 'super-' . uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'features' => null,
        ]);
        $user = User::create([
            'name' => 'Super', 'email' => 'humberto@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'email_verified_at' => now(),
        ]);

        $this->actingAs($user);
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue(ClientResource::canViewAny());
    }
}
