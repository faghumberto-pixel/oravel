<?php

namespace Tests\Feature;

use App\Filament\Resources\SolicitacaoLocacaoResource;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\InternalUnit;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pedido do usuário: a tela de Solicitar Equipamento não deve deixar
 * escolher um Patrimônio locado, precisa buscar por categoria primeiro e
 * mostrar quantos equipamentos equivalentes existem disponíveis e em qual
 * unidade.
 */
class SolicitacaoLocacaoEquipmentSearchTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Busca Equip '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_solicitacao_locacao', 'tabela_assets'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Busca Equip '.uniqid(), 'slug' => 'tenant-busca-equip-'.uniqid(),
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

    private function callAssetOptions(?string $categoryId, ?string $currentAssetId = null): array
    {
        $method = new \ReflectionMethod(SolicitacaoLocacaoResource::class, 'assetOptionsForCategory');
        $method->setAccessible(true);

        return $method->invoke(null, $categoryId, $currentAssetId);
    }

    public function test_asset_options_exclude_locado_and_reservado(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $disponivel = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador A', 'patrimonio' => 'PAT-A', 'status' => Asset::STATUS_DISPONIVEL, 'asset_category_id' => $category->id]);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador B', 'patrimonio' => 'PAT-B', 'status' => Asset::STATUS_LOCADO, 'asset_category_id' => $category->id]);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador C', 'patrimonio' => 'PAT-C', 'status' => Asset::STATUS_RESERVADO, 'asset_category_id' => $category->id]);
        $emManutencao = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador D', 'patrimonio' => 'PAT-D', 'status' => Asset::STATUS_MANUTENCAO, 'asset_category_id' => $category->id]);

        $options = $this->callAssetOptions($category->id);

        $this->assertCount(2, $options);
        $this->assertArrayHasKey($disponivel->id, $options);
        $this->assertArrayHasKey($emManutencao->id, $options);
        $this->assertStringContainsString('PAT-A', $options[$disponivel->id]);
    }

    public function test_asset_options_are_scoped_to_the_chosen_category(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $geradores = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $guindastes = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Guindastes']);
        $gerador = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador X', 'status' => Asset::STATUS_DISPONIVEL, 'asset_category_id' => $geradores->id]);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste Y', 'status' => Asset::STATUS_DISPONIVEL, 'asset_category_id' => $guindastes->id]);

        $options = $this->callAssetOptions($geradores->id);

        $this->assertCount(1, $options);
        $this->assertArrayHasKey($gerador->id, $options);
    }

    public function test_no_category_selected_returns_no_options(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $this->assertSame([], $this->callAssetOptions(null));
    }

    /**
     * Editar uma Solicitação antiga não pode "sumir" com o Ativo já salvo
     * mesmo que ele tenha ficado locado depois (ou nunca tenha batido no
     * backfill de categoria) -- ver docblock do método.
     */
    public function test_currently_selected_asset_stays_in_the_list_even_if_no_longer_eligible(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $jaLocado = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador Já Locado', 'patrimonio' => 'PAT-OLD', 'status' => Asset::STATUS_LOCADO, 'asset_category_id' => $category->id]);

        $options = $this->callAssetOptions($category->id, $jaLocado->id);

        $this->assertArrayHasKey($jaLocado->id, $options);
        $this->assertStringContainsString('fora do filtro', $options[$jaLocado->id]);
    }

    public function test_disponibilidade_panel_counts_by_status_and_internal_unit(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Geradores']);
        $matriz = InternalUnit::create(['tenant_id' => $tenant->id, 'name' => 'Matriz', 'type' => 'matriz']);
        $filial = InternalUnit::create(['tenant_id' => $tenant->id, 'name' => 'Filial Norte', 'type' => 'filial']);

        Asset::create(['tenant_id' => $tenant->id, 'name' => 'G1', 'status' => Asset::STATUS_DISPONIVEL, 'asset_category_id' => $category->id, 'internal_unit_id' => $matriz->id]);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'G2', 'status' => Asset::STATUS_MANUTENCAO, 'asset_category_id' => $category->id, 'internal_unit_id' => $filial->id]);
        Asset::create(['tenant_id' => $tenant->id, 'name' => 'G3', 'status' => Asset::STATUS_LOCADO, 'asset_category_id' => $category->id, 'internal_unit_id' => $matriz->id]);

        $method = new \ReflectionMethod(SolicitacaoLocacaoResource::class, 'disponibilidadeContent');
        $method->setAccessible(true);
        $html = $method->invoke(null, $category->id)->render();

        $this->assertStringContainsString('Matriz', $html);
        $this->assertStringContainsString('Filial Norte', $html);
    }
}
