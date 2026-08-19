<?php

namespace Tests\Feature\Api;

use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Achado de auditoria de segurança 2026-08-19: nenhum método de
 * Api\AssetController chamava Gate::authorize/AssetPolicy -- comentários no
 * próprio arquivo atribuíam essa proteção a um "TenantScope" morto (ver
 * project_analytics_subdomain_dns_orphan e o comentário corrigido em
 * AssetService::listarAssetPaginado). Isolamento por tenant funcionava (via
 * Concerns\BelongsToTenant), mas o gate de plano/permissão do AbstractPolicy
 * era completamente contornado -- qualquer usuário Sanctum autenticado do
 * tenant, mesmo sem a permissão granular editar_ativo/criar_ativo,
 * conseguia criar/editar/excluir Assets via API.
 */
class AssetControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithPlan(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Asset API '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Asset API '.uniqid(), 'slug' => 'tenant-asset-api-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        return [$plan, $tenant];
    }

    private function makeUserWithoutPermissions(Tenant $tenant): User
    {
        $user = User::create([
            'name' => 'Colaborador Sem Permissão', 'email' => 'colab-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        // Role sem nenhuma Permission concedida -- não é 'admin' (que
        // teria bypass total via isAdmin()) e não tem ler_ativo/criar_ativo/
        // editar_ativo/excluir_ativo.
        $user->assignRole(Role::firstOrCreate(['name' => 'colaborador_sem_acesso', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return $user;
    }

    public function test_index_rejects_authenticated_user_without_permission(): void
    {
        [, $tenant] = $this->makeTenantWithPlan();
        $user = $this->makeUserWithoutPermissions($tenant);

        Sanctum::actingAs($user);

        $this->getJson('/api/assets')->assertForbidden();
    }

    public function test_store_rejects_authenticated_user_without_permission(): void
    {
        [, $tenant] = $this->makeTenantWithPlan();
        $user = $this->makeUserWithoutPermissions($tenant);

        Sanctum::actingAs($user);

        $this->postJson('/api/assets', [
            'name' => 'Ativo Não Autorizado',
            'patrimonio' => 'PAT-'.uniqid(),
            'status' => 'disponivel',
        ])->assertForbidden();

        $this->assertDatabaseMissing('assets', ['name' => 'Ativo Não Autorizado']);
    }

    public function test_update_rejects_authenticated_user_without_permission(): void
    {
        [, $tenant] = $this->makeTenantWithPlan();
        $user = $this->makeUserWithoutPermissions($tenant);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Original', 'status' => 'disponivel']);

        Sanctum::actingAs($user);

        $this->putJson("/api/assets/{$asset->id}", ['name' => 'Ativo Alterado'])
            ->assertForbidden();

        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'name' => 'Ativo Original']);
    }

    public function test_destroy_rejects_authenticated_user_without_permission(): void
    {
        [, $tenant] = $this->makeTenantWithPlan();
        $user = $this->makeUserWithoutPermissions($tenant);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Protegido', 'status' => 'disponivel']);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/assets/{$asset->id}")->assertForbidden();

        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    public function test_tenant_admin_can_still_manage_assets(): void
    {
        [, $tenant] = $this->makeTenantWithPlan();

        $admin = User::create([
            'name' => 'Admin API', 'email' => 'admin-api-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        Sanctum::actingAs($admin);

        $this->getJson('/api/assets')->assertOk();

        $this->postJson('/api/assets', [
            'name' => 'Ativo do Admin',
            'patrimonio' => 'PAT-'.uniqid(),
            'status' => 'disponivel',
        ])->assertCreated();

        $this->assertDatabaseHas('assets', ['name' => 'Ativo do Admin', 'tenant_id' => $tenant->id]);
    }
}
