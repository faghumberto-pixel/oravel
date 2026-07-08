<?php

namespace Tests\Feature;

use App\Filament\Resources\SolicitacaoLocacaoResource;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TopbarBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_connected_tenant_name_appears_dynamically_below_logo(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Topbar '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_solicitacao_locacao'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Torres & Guindastes Teste', 'slug' => 'tenant-topbar-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $this->actingAs($admin);

        $response = $this->get(SolicitacaoLocacaoResource::getUrl('index', ['tenant' => $tenant->slug]));

        $response->assertOk();
        $response->assertSee('Torres &amp; Guindastes Teste', false);
    }

    public function test_different_tenants_show_their_own_name(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Topbar B '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_solicitacao_locacao'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Empresa Distinta XYZ', 'slug' => 'tenant-topbar-b-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $this->actingAs($admin);

        $response = $this->get(SolicitacaoLocacaoResource::getUrl('index', ['tenant' => $tenant->slug]));

        $response->assertOk();
        $response->assertSee('Empresa Distinta XYZ', false);
        $response->assertDontSee('Torres &amp; Guindastes Teste', false);
    }

    public function test_help_icon_points_to_academy(): void
    {
        $plan = Plan::create([
            'name' => 'Plano Ajuda '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_solicitacao_locacao'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Ajuda', 'slug' => 'tenant-ajuda-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        $this->actingAs($admin);

        $response = $this->get(SolicitacaoLocacaoResource::getUrl('index', ['tenant' => $tenant->slug]));

        $response->assertOk();
        $response->assertSee('https://academy.oravel.com.br/', false);
        $response->assertDontSee('mailto:suporte@oravel.com.br', false);
    }
}
