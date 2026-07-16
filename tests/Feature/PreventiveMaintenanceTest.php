<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenancePlanResource;
use App\Filament\Resources\PreventiveMaintenanceExecutionResource;
use App\Models\Asset;
use App\Models\ChecklistGroup;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderMaterial;
use App\Models\MaintenancePlan;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\PartsRequest;
use App\Models\Plan;
use App\Models\PreventiveMaintenanceExecution;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modulo de Manutencao Preventiva por Grupo de Ativo (horas trabalhadas) --
 * separado do checklist de inspecao (MaintenanceOrderChecklist) e do
 * checklist de mobilizacao (EquipmentMovementItemTemplate). MaintenancePlan
 * foi estendido (nao substituido) pra aceitar checklist_group_id alem do
 * asset_id legado.
 */
class PreventiveMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Preventiva '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_assets', 'tabela_maintenance_plans', 'tabela_preventive_maintenance_executions', 'tabela_materials', 'tabela_parts_requests'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Preventiva '.uniqid(), 'slug' => 'tenant-preventiva-'.uniqid(),
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

    private function makeGroupAndAsset(Tenant $tenant, float $horimetroAtual = 0): array
    {
        $group = ChecklistGroup::create(['tenant_id' => $tenant->id, 'name' => 'Geradores de Energia']);

        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador 1', 'tag' => 'AST-'.uniqid(),
            'patrimonio' => 'PAT-'.uniqid(), 'status' => 'disponivel',
            'checklist_group_id' => $group->id, 'horimetro_atual' => $horimetroAtual,
        ]);

        return [$group, $asset];
    }

    public function test_group_template_plan_is_overdue_when_asset_hours_pass_the_interval(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        [$group, $asset] = $this->makeGroupAndAsset($tenant, horimetroAtual: 300);

        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de óleo do motor', 'interval_hours' => 250, 'is_active' => true,
        ]);

        $status = $plan->dueStatusForAsset($asset->fresh());

        $this->assertTrue($status['is_overdue']);
        $this->assertSame(50.0, $status['overdue_hours']);
    }

    public function test_group_template_plan_uses_this_assets_own_execution_history_not_a_shared_value(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$group, $assetA] = $this->makeGroupAndAsset($tenant, horimetroAtual: 400);
        $assetB = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador 2', 'tag' => 'AST-'.uniqid(),
            'patrimonio' => 'PAT-'.uniqid(), 'status' => 'disponivel',
            'checklist_group_id' => $group->id, 'horimetro_atual' => 400,
        ]);

        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de óleo do motor', 'interval_hours' => 250, 'is_active' => true,
        ]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetA->id, 'technician_id' => $admin->id,
            'description' => 'Preventiva', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);

        // So o Ativo A recebeu a execucao -- o Ativo B nao deve herdar esse historico.
        PreventiveMaintenanceExecution::create([
            'tenant_id' => $tenant->id, 'asset_id' => $assetA->id, 'maintenance_plan_id' => $plan->id,
            'maintenance_order_id' => $order->id, 'technician_id' => $admin->id,
            'horimetro_at_execution' => 380,
        ]);

        $statusA = $plan->dueStatusForAsset($assetA->fresh());
        $statusB = $plan->dueStatusForAsset($assetB->fresh());

        $this->assertFalse($statusA['is_overdue']);
        $this->assertTrue($statusB['is_overdue']);
    }

    public function test_maintenance_plan_requires_asset_or_group_not_neither(): void
    {
        [$tenant] = $this->makeTenantAdmin();

        $this->expectException(QueryException::class);

        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'name' => 'Plano órfão', 'interval_hours' => 100, 'is_active' => true,
        ]);
    }

    public function test_executing_a_preventive_item_advances_the_assets_horimetro(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$group, $asset] = $this->makeGroupAndAsset($tenant, horimetroAtual: 300);

        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de óleo do motor', 'interval_hours' => 250, 'is_active' => true,
        ]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Preventiva', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);

        // horimetro_atual era dead-write antes desta feature -- nada no app avancava.
        $this->assertSame('300.00', $asset->fresh()->horimetro_atual);

        $execution = PreventiveMaintenanceExecution::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_plan_id' => $plan->id,
            'maintenance_order_id' => $order->id, 'technician_id' => $admin->id,
            'horimetro_at_execution' => 350,
        ]);

        $this->assertSame('350.00', $asset->fresh()->horimetro_atual);
        $this->assertSame('350.00', $asset->fresh()->last_horimetro);
        $this->assertSame('600.00', $execution->fresh()->next_due_horimetro);
    }

    private function makeMaterial(Tenant $tenant, array $overrides = []): Material
    {
        $category = MaterialCategory::create(['tenant_id' => $tenant->id, 'name' => 'Óleos']);

        return Material::create(array_merge([
            'tenant_id' => $tenant->id, 'sku' => 'SKU-'.uniqid(), 'name' => 'Óleo Motor 15W40',
            'material_category_id' => $category->id, 'unit_cost' => 50, 'price' => 80,
            'current_stock' => 10, 'min_stock' => 5, 'max_stock' => 20,
        ], $overrides));
    }

    public function test_material_requiring_serial_number_blocks_consumption_without_one(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo', 'status' => 'disponivel']);
        $material = $this->makeMaterial($tenant, ['requires_serial_number' => true]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'OS', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        MaintenanceOrderMaterial::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id,
            'material_id' => $material->id, 'quantity' => 1,
        ]);
    }

    public function test_consuming_material_decrements_stock_and_auto_creates_parts_request_when_low(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo', 'status' => 'disponivel']);
        $material = $this->makeMaterial($tenant, ['current_stock' => 6, 'min_stock' => 5]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'OS', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);

        MaintenanceOrderMaterial::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id,
            'material_id' => $material->id, 'quantity' => 2,
        ]);

        $this->assertEquals(4, $material->fresh()->current_stock);
        $this->assertSame(1, PartsRequest::where('material_id', $material->id)->count());
        $this->assertSame('pendente', PartsRequest::where('material_id', $material->id)->first()->status);
    }

    public function test_does_not_create_a_duplicate_parts_request_when_one_is_already_open(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo', 'status' => 'disponivel']);
        $material = $this->makeMaterial($tenant, ['current_stock' => 6, 'min_stock' => 5]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'OS', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);

        MaintenanceOrderMaterial::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id,
            'material_id' => $material->id, 'quantity' => 1,
        ]);
        MaintenanceOrderMaterial::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id,
            'material_id' => $material->id, 'quantity' => 1,
        ]);

        $this->assertSame(1, PartsRequest::where('material_id', $material->id)->count());
    }

    public function test_reapplying_same_serial_within_warranty_alerts_tenant_admins(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo', 'status' => 'disponivel']);
        $material = $this->makeMaterial($tenant, [
            'requires_serial_number' => true, 'warranty_days' => 90, 'current_stock' => 20,
        ]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'OS', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);

        MaintenanceOrderMaterial::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id,
            'material_id' => $material->id, 'quantity' => 1, 'serial_number' => 'SN-0001',
        ]);

        $this->assertSame(0, $admin->fresh()->notifications()->count());

        MaintenanceOrderMaterial::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id,
            'material_id' => $material->id, 'quantity' => 1, 'serial_number' => 'SN-0001',
        ]);

        $this->assertSame(1, $admin->fresh()->notifications()->count());
    }

    public function test_material_checklist_group_compatibility_pivot(): void
    {
        [$tenant] = $this->makeTenantAdmin();
        [$group] = $this->makeGroupAndAsset($tenant);
        $material = $this->makeMaterial($tenant);

        $material->checklistGroups()->attach($group->id);

        $this->assertTrue($material->fresh()->checklistGroups->contains($group->id));
    }

    public function test_preventivas_resource_index_and_create_pages_render(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$group, $asset] = $this->makeGroupAndAsset($tenant, horimetroAtual: 300);
        $plan = MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de óleo do motor', 'interval_hours' => 250, 'is_active' => true,
        ]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'Preventiva', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);
        PreventiveMaintenanceExecution::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'maintenance_plan_id' => $plan->id,
            'maintenance_order_id' => $order->id, 'technician_id' => $admin->id,
            'horimetro_at_execution' => 320,
        ]);

        $this->actingAs($admin);

        $this->get(PreventiveMaintenanceExecutionResource::getUrl())->assertOk();
        $this->get(PreventiveMaintenanceExecutionResource::getUrl('create'))->assertOk();
    }

    public function test_maintenance_plan_resource_index_and_create_pages_render(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        [$group] = $this->makeGroupAndAsset($tenant);
        MaintenancePlan::create([
            'tenant_id' => $tenant->id, 'checklist_group_id' => $group->id,
            'name' => 'Troca de óleo do motor', 'interval_hours' => 250, 'is_active' => true,
        ]);

        $this->actingAs($admin);

        $this->get(MaintenancePlanResource::getUrl())->assertOk();
        $this->get(MaintenancePlanResource::getUrl('create'))->assertOk();
    }
}
