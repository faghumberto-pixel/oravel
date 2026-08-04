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

    public function test_hour_meter_public_link_appears_when_asset_is_locado(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Locado Dossie', 'tag' => 'AST-LOC',
            'status' => Asset::STATUS_LOCADO,
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('assets.dossier.mobile', ['assetId' => $asset->id]));

        $response->assertOk();
        $response->assertSee('Link Público', false);

        $asset->refresh();
        $this->assertNotNull($asset->hour_meter_public_token);
        $response->assertSee(route('hour-meter.public.show', ['token' => $asset->hour_meter_public_token]), false);
    }

    public function test_hour_meter_public_link_does_not_appear_when_asset_is_not_locado(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador Disponivel Dossie', 'tag' => 'AST-DISP',
            'status' => Asset::STATUS_DISPONIVEL,
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('assets.dossier.mobile', ['assetId' => $asset->id]));

        $response->assertOk();
        $response->assertDontSee('Link Público', false);
        $this->assertNull($asset->fresh()->hour_meter_public_token);
    }

    /**
     * Regressao: o botao "Imprimir Etiqueta" (EditAsset::getHeaderActions())
     * usa uma rota de QR SEPARADA (asset.print-qr, SimpleSoftwareIO\QrCode)
     * da que fica dentro da aba "Rastreabilidade" do form (api.qrserver.com)
     * -- so corrigi essa segunda e esqueci da primeira, que continuava
     * gerando QR pra tela de edicao do painel (pesada, nao serve pro
     * celular no campo). E o botao mais visivel/usado na pratica pra
     * imprimir a etiqueta fisica do ativo. QR real (sem mock -- mockar o
     * facade SimpleSoftwareIO\QrCode quebra com "Cannot redeclare
     * Mockery_..._Generator::mockery_init()", bug conhecido da lib).
     */
    public function test_print_label_route_generates_a_real_qr_svg(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Ativo Etiqueta', 'tag' => 'AST-ETQ',
            'patrimonio' => 'PAT-ETIQUETA', 'status' => 'disponivel',
        ]);

        $this->actingAs($admin);

        $response = $this->get(route('asset.print-qr', ['tenant' => $tenant->slug, 'asset' => $asset->id]));

        $response->assertOk();
        $response->assertSee('PAT-ETIQUETA');
        $response->assertSee('<svg', false);
    }
}
