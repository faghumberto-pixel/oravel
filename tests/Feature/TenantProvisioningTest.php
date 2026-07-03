<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientResource;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisioning_creates_admin_user_scoped_to_tenant(): void
    {
        $plan = Plan::create([
            'name' => 'Plano provisionamento', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant provisionado', 'slug' => 'provisionado-' . uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'features' => null,
        ]);

        $user = TenantProvisioner::provision($tenant, [
            'name' => 'Dono da Empresa',
            'email' => 'dono-' . uniqid() . '@oravel.com.br',
            'password' => 'senha12345',
        ]);

        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertTrue($user->isAdmin());
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('senha12345', $user->password));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_provisioned_admin_has_full_access_to_everything_the_plan_grants(): void
    {
        // O admin criado no provisionamento deve, sem nenhuma Permission
        // explicita, ja conseguir acessar tudo que o plano contratado libera
        // -- isAdmin() da bypass total na trava de permissao individual em
        // AbstractPolicy::check(), so a trava comercial (feature do plano)
        // continua valendo.
        $plan = Plan::create([
            'name' => 'Plano provisionamento 2', 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_clients'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant provisionado 2', 'slug' => 'provisionado2-' . uniqid(),
            'plan_id' => $plan->id, 'status' => 'active', 'features' => null,
        ]);

        $user = TenantProvisioner::provision($tenant, [
            'name' => 'Dono 2',
            'email' => 'dono2-' . uniqid() . '@oravel.com.br',
            'password' => 'senha12345',
        ]);

        $this->actingAs($user);
        $this->assertTrue(ClientResource::canViewAny());
        $this->assertTrue(ClientResource::canCreate());
    }

    public function test_plan_available_features_options_are_generated_from_saas_registry(): void
    {
        $options = Plan::getAvailableFeaturesOptions();

        $this->assertArrayHasKey('tabela_clients', $options);
        $this->assertArrayHasKey('modulo_chat', $options);
        // Modelos que so ganharam metadados SaaS nesta correcao (antes ficavam
        // de fora da tela de contratacao de plano na Central).
        $this->assertArrayHasKey('tabela_assets', $options);
        $this->assertArrayHasKey('tabela_suppliers', $options);
        $this->assertArrayHasKey('tabela_abc_matrix', $options);
        $this->assertArrayHasKey('tabela_checklist_groups', $options);
    }
}
