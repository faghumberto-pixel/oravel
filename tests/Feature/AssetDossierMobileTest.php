<?php

namespace Tests\Feature;

use App\Livewire\AssetDossierMobile;
use App\Models\Asset;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Versao pro campo/patio do Dossie Rapido -- destino do QR code do ativo.
 * Mesma logica de busca flexivel e isolamento por tenant da versao desktop
 * (App\Filament\Pages\AssetDossier), so o visual muda.
 */
class AssetDossierMobileTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Mobile '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_assets'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Mobile '.uniqid(), 'slug' => 'tenant-mobile-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin->assignRole($role);

        return [$tenant, $admin];
    }

    public function test_direct_link_via_route_shows_the_asset(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Guindaste Mobile', 'tag' => 'AST-1',
            'patrimonio' => 'PAT-MOBILE-001', 'status' => 'disponivel',
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('assets.dossier.mobile', ['assetId' => $asset->id]));

        $response->assertOk();
        $response->assertSee('Guindaste Mobile');
        $response->assertSee('PAT-MOBILE-001');
    }

    public function test_without_asset_id_shows_the_search_box(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $response = $this->get(route('assets.dossier.mobile'));

        $response->assertOk();
        $response->assertSee('Buscar Ativo');
    }

    public function test_flexible_search_with_single_match_opens_it_directly(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Compressor Único', 'tag' => 'AST-2',
            'patrimonio' => 'PAT-UNICO', 'status' => 'disponivel',
        ]);

        $this->actingAs($admin);

        Livewire::test(AssetDossierMobile::class)
            ->set('query', 'unico')
            ->call('search')
            ->assertSet('asset.id', $asset->id);
    }

    public function test_search_with_multiple_matches_shows_a_picker_list(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeira A', 'tag' => 'AST-A', 'patrimonio' => 'PAT-A', 'status' => 'disponivel']);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeira B', 'tag' => 'AST-B', 'patrimonio' => 'PAT-B', 'status' => 'disponivel']);

        $this->actingAs($admin);

        $component = Livewire::test(AssetDossierMobile::class)
            ->set('query', 'Empilhadeira')
            ->call('search');

        $component->assertSet('asset', null);
        $this->assertCount(2, $component->get('searchResults'));
    }

    public function test_does_not_leak_another_tenants_asset(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        $assetA = Asset::create([
            'tenant_id' => $tenantA->id, 'name' => 'Ativo Sigiloso', 'tag' => 'AST-S',
            'patrimonio' => 'PAT-SIGILOSO', 'status' => 'disponivel',
        ]);

        [$tenantB, $adminB] = $this->makeTenantAdmin();

        $this->actingAs($adminB);

        $response = $this->get(route('assets.dossier.mobile', ['assetId' => $assetA->id]));

        // Sem o assets scoped ao tenant do admin B, o find() nao acha nada --
        // a tela cai pra busca vazia, nao vaza o nome/patrimonio do outro tenant.
        $response->assertOk();
        $response->assertDontSee('Ativo Sigiloso');
        $response->assertDontSee('PAT-SIGILOSO');
    }
}
