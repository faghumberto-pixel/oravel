<?php

namespace Tests\Feature;

use App\Filament\Pages\PainelGestao;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pedido do usuario (2026-07-10): esconder o Dashboard (PainelGestao) pra um
 * tenant especifico -- ate aqui essa pagina nao tinha canAccess() nenhum,
 * entao nao existia nenhum jeito de desliga-la pela Central. 'modulo_dashboard'
 * e' uma feature "sintetica" (nao tem Model/tabela por tras, so controla essa
 * pagina) registrada manualmente em Plan::getAvailableFeaturesOptions().
 */
class DashboardFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(array $features): array
    {
        $plan = Plan::create([
            'name' => 'Plano Dashboard '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features,
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Dashboard '.uniqid(), 'slug' => 'tenant-dash-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_dashboard_visible_when_plan_grants_modulo_dashboard(): void
    {
        [, $admin] = $this->makeTenant(['tabela_assets', 'modulo_dashboard']);
        $this->actingAs($admin);

        $this->assertTrue(PainelGestao::canAccess());
    }

    public function test_dashboard_hidden_when_plan_does_not_grant_modulo_dashboard(): void
    {
        [, $admin] = $this->makeTenant(['tabela_assets']);
        $this->actingAs($admin);

        $this->assertFalse(PainelGestao::canAccess());
    }

    public function test_dashboard_visible_when_there_is_no_tenant_context(): void
    {
        $super = User::create([
            'name' => 'Super', 'email' => 'super-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => null,
        ]);
        $super->forceFill(['email_verified_at' => now()])->save();
        config(['oravel.super_admins' => [$super->email]]);

        $this->actingAs($super);

        $this->assertTrue(PainelGestao::canAccess());
    }

    public function test_modulo_dashboard_appears_in_available_features_options(): void
    {
        $this->assertArrayHasKey('modulo_dashboard', Plan::getAvailableFeaturesOptions());
    }

    /**
     * Cobertura de render que faltava: com o redesenho pro mesmo padrão
     * visual do Dashboard PMP (wrapper .dark + segmented control custom
     * pras abas), garante que as duas abas ainda renderizam sem erro de
     * blade -- pega quebra de sintaxe/duplicação de div antes de ir pro ar.
     */
    public function test_dashboard_renders_both_tabs_without_errors(): void
    {
        [, $admin] = $this->makeTenant(['tabela_assets', 'modulo_dashboard']);
        $this->actingAs($admin);

        $this->get(PainelGestao::getUrl())->assertOk();

        $component = Livewire::test(PainelGestao::class)
            ->assertSee('Painel de Gestão')
            ->assertSee('Centro de Comando');

        $component->call('selectTab', 'comando')
            ->assertSet('activeTab', 'comando')
            ->assertSuccessful()
            ->assertSee('Minhas Ordens de Serviço');
    }

    public function test_backfill_migration_grants_modulo_dashboard_to_existing_plans_preserving_format(): void
    {
        $planList = Plan::create([
            'name' => 'Plano Lista '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);
        $planDict = Plan::create([
            'name' => 'Plano Dicionario '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
        ]);
        // Simula formato antigo (dicionario), gravando direto no banco pra nao
        // passar pelo mutator do Model (que normaliza pra JSON qualquer forma).
        DB::table('plans')->where('id', $planDict->id)
            ->update(['features' => json_encode(['tabela_assets' => true])]);

        $migration = require base_path('database/migrations/2026_07_10_090300_backfill_modulo_dashboard_feature_on_plans.php');
        $migration->up();

        $this->assertTrue(in_array('modulo_dashboard', $planList->fresh()->features, true));
        $this->assertTrue($planList->fresh()->hasFeature('tabela_assets'), 'nao deveria perder a feature que ja tinha');

        $this->assertTrue($planDict->fresh()->hasFeature('modulo_dashboard'));
        $this->assertTrue($planDict->fresh()->hasFeature('tabela_assets'), 'nao deveria perder a feature que ja tinha (formato dicionario)');
    }
}
