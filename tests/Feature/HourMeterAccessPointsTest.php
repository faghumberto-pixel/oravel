<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pontos de acesso reais pras 3 telas de horimetro (mobile offline, dossie,
 * link publico) -- antes so existiam as rotas, sem nenhum jeito de chegar
 * nelas a partir do painel (usuario reportou nao achar nada, 2026-08-04).
 */
class HourMeterAccessPointsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Acesso '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Acesso '.uniqid(), 'slug' => 'tenant-acesso-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_hour_meter_menu_item_appears_in_the_admin_panel_sidebar(): void
    {
        [, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $response = $this->get('/admin/assets');

        $response->assertOk();
        $response->assertSee('Registrar Horímetro');
        $response->assertSee(route('hour-meter.offline'), false);
    }

    public function test_asset_list_has_a_dossier_action_link_for_each_row(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Acesso', 'tag' => 'AST-'.uniqid(),
            'status' => Asset::STATUS_DISPONIVEL,
        ]);
        $this->actingAs($admin);

        Livewire::test(ListAssets::class)
            ->assertTableActionExists('dossie_horimetro')
            ->assertTableActionHasUrl('dossie_horimetro', route('assets.dossier.mobile', ['assetId' => $asset->id]), $asset);
    }
}
