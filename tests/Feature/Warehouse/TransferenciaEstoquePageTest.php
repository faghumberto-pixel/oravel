<?php

namespace Tests\Feature\Warehouse;

use App\Filament\Pages\TransferenciaEstoque;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Interface administrativa pra StockTransferService::transferToMobile() --
 * a página que faltava chamar o Service (ver
 * project_almoxarifado_volante_mobile_warehouses).
 */
class TransferenciaEstoquePageTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithAdminAndWarehouses(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Transferência '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_warehouses', 'tabela_parts', 'tabela_stock_movements'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Transferência '.uniqid(), 'slug' => 'tenant-transferencia-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole($adminRole);

        $technician = User::create([
            'name' => 'Técnico', 'email' => 'tecnico-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        $central = Warehouse::create(['tenant_id' => $tenant->id, 'name' => 'Central', 'type' => Warehouse::TYPE_CENTRAL]);
        $mobile = Warehouse::create([
            'tenant_id' => $tenant->id, 'name' => 'Van 01', 'type' => Warehouse::TYPE_MOBILE,
            'user_id' => $technician->id, 'vehicle_plate' => 'XYZ9A87',
        ]);

        $category = PartCategory::create(['tenant_id' => $tenant->id, 'name' => 'Correias', 'slug' => 'correias-'.uniqid()]);
        $part = Part::create([
            'tenant_id' => $tenant->id, 'part_category_id' => $category->id,
            'sku' => 'SKU-'.uniqid(), 'name' => 'Correia Dentada', 'unit_of_measure' => 'UN', 'cost_price' => 30,
        ]);

        WarehouseStock::create(['warehouse_id' => $central->id, 'part_id' => $part->id, 'current_quantity' => 50]);

        return [$tenant, $admin, $central, $mobile, $part];
    }

    public function test_admin_can_transfer_stock_to_mobile_warehouse(): void
    {
        [, $admin, $central, $mobile, $part] = $this->makeTenantWithAdminAndWarehouses();

        $this->actingAs($admin);

        Livewire::test(TransferenciaEstoque::class)
            ->fillForm([
                'source_warehouse_id' => $central->id,
                'destination_warehouse_id' => $mobile->id,
                'items' => [
                    ['part_id' => $part->id, 'quantity' => 12],
                ],
            ])
            ->call('transferir')
            ->assertHasNoFormErrors();

        $centralStock = WarehouseStock::where('warehouse_id', $central->id)->where('part_id', $part->id)->first();
        $mobileStock = WarehouseStock::where('warehouse_id', $mobile->id)->where('part_id', $part->id)->first();

        $this->assertEqualsWithDelta(38.0, (float) $centralStock->current_quantity, 0.01);
        $this->assertEqualsWithDelta(12.0, (float) $mobileStock->current_quantity, 0.01);
    }

    public function test_shows_error_notification_when_stock_insufficient(): void
    {
        [, $admin, $central, $mobile, $part] = $this->makeTenantWithAdminAndWarehouses();

        $this->actingAs($admin);

        Livewire::test(TransferenciaEstoque::class)
            ->fillForm([
                'source_warehouse_id' => $central->id,
                'destination_warehouse_id' => $mobile->id,
                'items' => [
                    ['part_id' => $part->id, 'quantity' => 999],
                ],
            ])
            ->call('transferir');

        $centralStock = WarehouseStock::where('warehouse_id', $central->id)->where('part_id', $part->id)->first();
        $this->assertEqualsWithDelta(50.0, (float) $centralStock->current_quantity, 0.01);
    }
}
