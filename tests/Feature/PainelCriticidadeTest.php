<?php

namespace Tests\Feature;

use App\Filament\Pages\PainelCriticidade;
use App\Models\AbcMatrix;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PainelCriticidadeTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Criticidade '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_plans', 'tabela_abc_matrix'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Criticidade '.uniqid(), 'slug' => 'tenant-criticidade-'.uniqid(),
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

    public function test_asset_with_overdue_preventiva_and_abc_nivel_a_shows_as_critico(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Crítico', 'status' => 'disponivel',
            'horimetro_atual' => 500,
        ]);

        AbcMatrix::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'nivel' => 'A', 'descricao' => 'Essencial']);

        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'name' => 'Troca de óleo', 'interval_hours' => 100, 'last_service_hours' => 100,
        ]);

        $this->actingAs($admin);

        $response = $this->get(PainelCriticidade::getUrl());

        $response->assertOk();
        $response->assertSee('Gerador Crítico');
        $response->assertSee('Crítico');
        $response->assertSee('Nível A');
        $response->assertSee('Vencida');
    }

    public function test_asset_with_no_alerts_does_not_appear(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();

        Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Tranquilo', 'status' => 'disponivel', 'horimetro_atual' => 10]);

        $this->actingAs($admin);

        $response = $this->get(PainelCriticidade::getUrl());

        $response->assertOk();
        $response->assertSee('Nenhum ativo com alerta no momento.');
        $response->assertDontSee('Ativo Tranquilo');
    }

    public function test_panel_does_not_leak_another_tenants_critical_asset(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        $assetA = Asset::create(['tenant_id' => $tenantA->id, 'name' => 'Ativo Tenant A', 'status' => 'disponivel', 'horimetro_atual' => 500]);
        MaintenancePlan::create(['tenant_id' => $tenantA->id, 'asset_id' => $assetA->id, 'name' => 'Troca de óleo', 'interval_hours' => 100, 'last_service_hours' => 100]);

        [$tenantB, $adminB] = $this->makeTenantAdmin();

        $this->actingAs($adminB);

        $response = $this->get(PainelCriticidade::getUrl());

        $response->assertOk();
        $response->assertDontSee('Ativo Tenant A');
    }
}
