<?php

namespace Tests\Feature;

use App\Filament\Resources\MaintenanceOrderResource\Pages\EditMaintenanceOrder;
use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Material;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Confirmado 2026-07-29: os 4 campos de custo da O.S. (labor_cost/
 * material_cost/logistics_cost/total_order_cost) eram TextInput 100% manual,
 * sem calculo automatico nenhum e sem trava por papel -- um tecnico
 * conseguia digitar qualquer valor direto na O.S. Este teste cobre:
 * material_cost/total_order_cost calculados de verdade a partir dos
 * materiais aplicados, e labor_cost/logistics_cost travados pra quem nao e
 * admin.
 */
class MaintenanceOrderCostAutomationTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Custos '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_maintenance_orders', 'tabela_materials'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Custos '.uniqid(), 'slug' => 'tenant-custos-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    private function makeUser(Tenant $tenant, string $roleName): User
    {
        $user = User::create([
            'name' => ucfirst($roleName), 'email' => $roleName.'-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole(Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return $user;
    }

    public function test_material_cost_and_total_are_calculated_from_applied_materials(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'admin');
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Custo', 'status' => 'disponivel']);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'OS custo',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'em_manutencao',
            'labor_cost' => 100, 'logistics_cost' => 50,
        ]);
        $material = Material::create([
            'tenant_id' => $tenant->id, 'sku' => 'SKU-'.uniqid(), 'name' => 'Filtro de óleo', 'unit_cost' => 25.50,
            'current_stock' => 10, 'min_stock' => 1,
        ]);

        $this->actingAs($admin);

        $order->materials()->create(['material_id' => $material->id, 'quantity' => 3]);

        $order->refresh();
        $this->assertEquals(76.50, (float) $order->material_cost); // 3 * 25.50
        $this->assertEquals(226.50, (float) $order->total_order_cost); // 100 + 76.50 + 50

        // Remover o material tem que refletir de volta.
        $order->materials()->first()->delete();
        $order->refresh();
        $this->assertEquals(0.0, (float) $order->material_cost);
        $this->assertEquals(150.0, (float) $order->total_order_cost); // 100 + 0 + 50
    }

    public function test_total_order_cost_recalculates_when_labor_or_logistics_changes(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser($tenant, 'admin');
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Custo 2', 'status' => 'disponivel']);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'OS custo 2',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'em_manutencao',
            'labor_cost' => 100, 'logistics_cost' => 50,
        ]);

        $this->actingAs($admin);

        $order->update(['labor_cost' => 200]);
        $order->refresh();
        $this->assertEquals(250.0, (float) $order->total_order_cost); // 200 + 0 + 50
    }

    public function test_technician_cannot_edit_labor_or_logistics_cost_but_admin_can(): void
    {
        $tenant = $this->makeTenant();
        $technician = $this->makeUser($tenant, 'tecnico');
        Permission::firstOrCreate(['name' => 'ler_ordem_servico', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'editar_ordem_servico', 'guard_name' => 'web']);
        $technician->givePermissionTo(['ler_ordem_servico', 'editar_ordem_servico']);
        $admin = $this->makeUser($tenant, 'admin');
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Ativo Custo 3', 'status' => 'disponivel']);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'description' => 'OS custo 3',
            'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE, 'internal_status' => 'em_manutencao',
            'labor_cost' => 10, 'logistics_cost' => 5,
        ]);

        $this->actingAs($technician);
        Livewire::test(EditMaintenanceOrder::class, ['record' => $order->id])
            ->assertFormFieldIsDisabled('labor_cost')
            ->assertFormFieldIsDisabled('logistics_cost')
            ->assertFormFieldIsDisabled('material_cost')
            ->assertFormFieldIsDisabled('total_order_cost');

        $this->actingAs($admin);
        Livewire::test(EditMaintenanceOrder::class, ['record' => $order->id])
            ->assertFormFieldIsEnabled('labor_cost')
            ->assertFormFieldIsEnabled('logistics_cost')
            ->assertFormFieldIsDisabled('material_cost')
            ->assertFormFieldIsDisabled('total_order_cost');
    }
}
