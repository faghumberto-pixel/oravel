<?php

namespace Tests\Feature;

use App\Filament\Resources\AssetResource\Pages\EditAsset;
use App\Filament\Resources\AssetResource\RelationManagers\MaintenancePlansRelationManager;
use App\Models\Asset;
use App\Models\ChecklistGroup;
use App\Models\MaintenancePlan;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetMaintenancePlansRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): array
    {
        $plan = Plan::create([
            'name' => 'Plano PMP Ativo '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_plans'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant PMP Ativo '.uniqid(), 'slug' => 'tenant-pmp-ativo-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_itens_herdados_do_grupo_aparecem_sem_precisar_personalizar(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeiras']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Empilhadeira 1', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de óleo hidráulico', 'interval_hours' => 500,
        ]);

        $this->actingAs($admin);

        Livewire::test(MaintenancePlansRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass' => EditAsset::class,
        ])->assertSee('Troca de óleo hidráulico');
    }

    public function test_item_personalizado_do_ativo_substitui_o_do_grupo_sem_duplicar(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeiras']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Empilhadeira 1', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de óleo hidráulico', 'interval_hours' => 500,
        ]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'name' => 'Troca de óleo hidráulico', 'interval_hours' => 300, 'source' => MaintenancePlan::SOURCE_TEMPLATE,
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(MaintenancePlansRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass' => EditAsset::class,
        ]);

        $reflection = new \ReflectionMethod($component->instance(), 'getTableRecords');
        $reflection->setAccessible(true);
        $records = $reflection->invoke($component->instance());

        $this->assertCount(1, $records->where('name', 'Troca de óleo hidráulico'));
    }

    public function test_item_manual_proprio_do_ativo_aparece_junto(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeira Solo', 'status' => Asset::STATUS_DISPONIVEL]);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id,
            'name' => 'Item exclusivo deste ativo', 'interval_hours' => 100, 'source' => MaintenancePlan::SOURCE_MANUAL,
        ]);

        $this->actingAs($admin);

        Livewire::test(MaintenancePlansRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass' => EditAsset::class,
        ])->assertSee('Item exclusivo deste ativo');
    }

    public function test_personalizar_item_herdado_cria_copia_editavel_do_ativo(): void
    {
        [$tenant, $admin] = $this->makeTenant();
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeiras']);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Empilhadeira 1', 'status' => Asset::STATUS_DISPONIVEL,
            'checklist_group_id' => $group->id,
        ]);
        $templateItem = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de óleo hidráulico', 'interval_hours' => 500,
        ]);

        $this->actingAs($admin);

        Livewire::test(MaintenancePlansRelationManager::class, [
            'ownerRecord' => $asset,
            'pageClass' => EditAsset::class,
        ])
            ->callTableAction('personalizar', $templateItem)
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('maintenance_plans', [
            'asset_id' => $asset->id,
            'name' => 'Troca de óleo hidráulico',
            'interval_hours' => 500,
        ]);
    }
}
