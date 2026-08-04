<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Client;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /admin/hour-meter (pagina Blade+Alpine offline-first) e
 * GET /api/v1/hour-meters/preload (dados que a pagina guarda em
 * localStorage pra busca funcionar sem rede).
 */
class HourMeterOfflinePageTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano HourMeter '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_horimeter_readings'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant HourMeter '.uniqid(), 'slug' => 'tenant-hourmeter-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Tecnico Campo', 'email' => 'tecnico-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_page_requires_authentication(): void
    {
        $response = $this->get('/admin/hour-meter');

        $response->assertRedirect();
    }

    public function test_page_renders_technician_name_for_authenticated_user(): void
    {
        [, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $response = $this->get('/admin/hour-meter');

        $response->assertOk();
        $response->assertSee($admin->name);
        $response->assertSee('hourMeterOffline', false);
    }

    public function test_preload_returns_only_current_tenant_assets_with_last_reading(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $otherTenant = Tenant::create([
            'name' => 'Outro Tenant '.uniqid(), 'slug' => 'outro-'.uniqid(),
            'plan_id' => $tenant->plan_id, 'status' => 'active',
        ]);

        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente X']);

        Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador A', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel', 'horimetro_atual' => 321.5, 'client_id' => $client->id,
        ]);
        Asset::create([
            'tenant_id' => $otherTenant->id, 'name' => 'Gerador de Outro Tenant', 'tag' => 'AST-'.uniqid(),
            'status' => 'disponivel', 'horimetro_atual' => 999,
        ]);

        $this->actingAs($admin);

        $response = $this->getJson('/api/v1/hour-meters/preload');

        $response->assertOk();
        $assets = $response->json('assets');
        $this->assertCount(1, $assets);
        $this->assertSame('Gerador A', $assets[0]['name']);
        $this->assertSame('321.50', $assets[0]['last_reading']);
        $this->assertSame('Cliente X', $assets[0]['client_name']);
    }

    public function test_preload_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/hour-meters/preload');

        $response->assertUnauthorized();
    }
}
