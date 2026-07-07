<?php

namespace Tests\Feature;

use App\Filament\Pages\MaintenanceKanban;
use App\Filament\Resources\AssetResource\Pages\ListAssets;
use App\Livewire\EquipmentMovementMobile;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementItemTemplate;
use App\Models\MaintenanceOrder;
use App\Models\Plan;
use App\Models\Role;
use App\Models\SolicitacaoLocacao;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * As 4 funcionalidades construidas pra viabilizar os 8 cenarios de demo
 * comercial (ver DemoTenantsSeeder): (A) destaque de urgencia no Kanban,
 * (B) status Aguardando Triagem + auto-flip pos-desmobilizacao, (C)
 * SolicitacaoLocacao multi-ativo (combo), (D) acao em massa no AssetResource.
 */
class DemoCommercialTriggersTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Demo Trigger '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_orders', 'tabela_solicitacao_locacao', 'tabela_equipment_movements'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Demo Trigger '.uniqid(), 'slug' => 'tenant-demo-trigger-'.uniqid(),
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

    private function makeSolicitacao(Tenant $tenant, User $user, array $overrides = []): SolicitacaoLocacao
    {
        $client = Client::create(['tenant_id' => $tenant->id, 'name' => 'Cliente '.uniqid()]);
        $category = AssetCategory::create(['tenant_id' => $tenant->id, 'name' => 'Categoria '.uniqid()]);

        return SolicitacaoLocacao::create(array_merge([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'customer_id' => $client->id,
            'category_id' => $category->id,
            'data_saida_prevista' => now()->addDay(),
            'status_comercial' => 'proposta_em_andamento',
        ], $overrides));
    }

    // --- Feature A: destaque de urgencia no Kanban ---

    public function test_kanban_flags_asset_with_pending_urgent_rental_request(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $urgentAsset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Urgente', 'status' => 'manutencao']);
        $normalAsset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Normal', 'status' => 'manutencao']);

        $this->makeSolicitacao($tenant, $admin, ['asset_id' => $urgentAsset->id, 'status_comercial' => 'reserva_manutencao']);
        $this->makeSolicitacao($tenant, $admin, ['asset_id' => $normalAsset->id, 'status_comercial' => 'proposta_em_andamento']);

        $this->actingAs($admin);

        $component = Livewire::test(MaintenanceKanban::class);
        $urgentIds = $component->instance()->getUrgentAssetIds();

        $this->assertContains($urgentAsset->id, $urgentIds);
        $this->assertNotContains($normalAsset->id, $urgentIds);
    }

    public function test_kanban_urgent_highlight_does_not_leak_across_tenants(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        $assetA = Asset::create(['tenant_id' => $tenantA->id, 'name' => 'Ativo A', 'status' => 'manutencao']);
        $this->makeSolicitacao($tenantA, $adminA, ['asset_id' => $assetA->id, 'status_comercial' => 'reserva_manutencao']);

        [$tenantB, $adminB] = $this->makeTenantAdmin();

        $this->actingAs($adminB);
        $component = Livewire::test(MaintenanceKanban::class);
        $urgentIds = $component->instance()->getUrgentAssetIds();

        $this->assertNotContains($assetA->id, $urgentIds);
    }

    // --- Feature B: Aguardando Triagem + auto-flip pos-desmobilizacao ---

    public function test_creating_a_desmobilization_sets_asset_to_aguardando_triagem(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo', 'status' => 'locado']);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Retorno', 'maintenance_type' => MaintenanceOrder::TYPE_CHECKIN,
        ]);

        $this->actingAs($admin);

        Livewire::test(EquipmentMovementMobile::class, [
            'maintenanceOrder' => $order,
            'type' => EquipmentMovement::TYPE_DESMOBILIZACAO,
        ]);

        $this->assertSame(Asset::STATUS_AGUARDANDO_TRIAGEM, $asset->fresh()->status);
    }

    public function test_finalizing_desmobilization_flips_asset_back_to_disponivel(): void
    {
        EquipmentMovementItemTemplate::create([
            'type' => EquipmentMovement::TYPE_DESMOBILIZACAO, 'section' => 'Geral',
            'label' => 'Limpeza geral', 'sort_order' => 1, 'requires_photo' => false,
        ]);

        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo', 'status' => 'locado']);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Retorno', 'maintenance_type' => MaintenanceOrder::TYPE_CHECKIN,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(EquipmentMovementMobile::class, [
            'maintenanceOrder' => $order,
            'type' => EquipmentMovement::TYPE_DESMOBILIZACAO,
        ]);

        $this->assertSame(Asset::STATUS_AGUARDANDO_TRIAGEM, $asset->fresh()->status);

        $item = $component->instance()->equipmentMovement->items()->first();
        $component->call('toggleItem', $item->id)
            ->assertSet('progress', 100)
            ->call('finalize');

        $this->assertSame(Asset::STATUS_DISPONIVEL, $asset->fresh()->status);
    }

    // --- Feature C: SolicitacaoLocacao multi-ativo (combo) ---

    public function test_solicitacao_combo_tracks_multiple_assets_and_kit_completeness(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $assetA = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo A', 'status' => 'disponivel']);
        $assetB = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo B', 'status' => 'manutencao']);

        $solicitacao = $this->makeSolicitacao($tenant, $admin);
        $solicitacao->assets()->attach([$assetA->id, $assetB->id]);

        $this->assertSame(2, $solicitacao->assets()->count());
        $this->assertFalse($solicitacao->isKitComplete());

        $assetB->update(['status' => Asset::STATUS_DISPONIVEL]);
        $this->assertTrue($solicitacao->fresh()->isKitComplete());
    }

    public function test_solicitacao_combo_does_not_leak_assets_across_tenants(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        $assetA = Asset::create(['tenant_id' => $tenantA->id, 'name' => 'Ativo A', 'status' => 'disponivel']);
        $solicitacaoA = $this->makeSolicitacao($tenantA, $adminA);
        $solicitacaoA->assets()->attach([$assetA->id]);

        [$tenantB] = $this->makeTenantAdmin();
        $this->assertSame(0, SolicitacaoLocacao::where('tenant_id', $tenantB->id)->count());
    }

    // --- Feature D: acao em massa no AssetResource ---

    public function test_bulk_changing_asset_status_updates_all_selected_records(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $assets = collect(range(1, 3))->map(fn (int $i) => Asset::create([
            'tenant_id' => $tenant->id, 'name' => "Ativo {$i}", 'status' => Asset::STATUS_DISPONIVEL,
        ]));

        $this->actingAs($admin);

        Livewire::test(ListAssets::class)
            ->callTableBulkAction('alterar_status_em_massa', $assets, data: ['status' => Asset::STATUS_LOCADO]);

        $this->assertTrue($assets->map->fresh()->every(fn (Asset $a) => $a->status === Asset::STATUS_LOCADO));
    }

    public function test_bulk_status_change_does_not_affect_another_tenants_assets(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        $assetA = Asset::create(['tenant_id' => $tenantA->id, 'name' => 'Ativo A', 'status' => Asset::STATUS_DISPONIVEL]);

        [$tenantB, $adminB] = $this->makeTenantAdmin();
        $assetB = Asset::create(['tenant_id' => $tenantB->id, 'name' => 'Ativo B', 'status' => Asset::STATUS_DISPONIVEL]);

        $this->actingAs($adminA);

        Livewire::test(ListAssets::class)
            ->callTableBulkAction('alterar_status_em_massa', [$assetA], data: ['status' => Asset::STATUS_LOCADO]);

        $this->assertSame(Asset::STATUS_LOCADO, $assetA->fresh()->status);
        $this->assertSame(Asset::STATUS_DISPONIVEL, $assetB->fresh()->status);
    }
}
