<?php

namespace Tests\Feature\Warehouse;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Plan;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Almoxarifado Volante: transferência do depósito central pro veículo do
 * técnico, e apropriação direta na O.S. -- ponte nova entre Part/Warehouse
 * (Almoxarifado) e MaintenanceOrder, que não existia antes desta feature.
 */
class MobileWarehouseStockTransferTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantWithTechnician(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Volante '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_warehouses', 'tabela_parts', 'tabela_stock_movements', 'tabela_maintenance_orders'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Volante '.uniqid(), 'slug' => 'tenant-volante-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);

        $technician = User::create([
            'name' => 'Técnico de Campo', 'email' => 'tecnico-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $technician->forceFill(['email_verified_at' => now()])->save();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->assignRole($adminRole);

        return [$tenant, $technician, $admin];
    }

    private function makePart(Tenant $tenant, float $costPrice = 50.0): Part
    {
        $category = PartCategory::create(['tenant_id' => $tenant->id, 'name' => 'Filtros', 'slug' => 'filtros-'.uniqid()]);

        return Part::create([
            'tenant_id' => $tenant->id, 'part_category_id' => $category->id,
            'sku' => 'SKU-'.uniqid(), 'name' => 'Filtro de Óleo',
            'unit_of_measure' => 'UN', 'cost_price' => $costPrice,
        ]);
    }

    private function makeWarehouses(Tenant $tenant, User $technician): array
    {
        $central = Warehouse::create([
            'tenant_id' => $tenant->id, 'name' => 'Depósito Central', 'code' => 'ALM-01',
            'type' => Warehouse::TYPE_CENTRAL,
        ]);

        $mobile = Warehouse::create([
            'tenant_id' => $tenant->id, 'name' => 'Van do Técnico', 'code' => 'VAN-01',
            'type' => Warehouse::TYPE_MOBILE, 'user_id' => $technician->id, 'vehicle_plate' => 'ABC1D23',
        ]);

        return [$central, $mobile];
    }

    public function test_transfer_to_mobile_moves_stock_between_warehouses(): void
    {
        [$tenant, $technician, $admin] = $this->makeTenantWithTechnician();
        [$central, $mobile] = $this->makeWarehouses($tenant, $technician);
        $part = $this->makePart($tenant, 50.0);

        WarehouseStock::create(['warehouse_id' => $central->id, 'part_id' => $part->id, 'current_quantity' => 100]);

        app(StockTransferService::class)->transferToMobile($central->id, $mobile->id, [
            ['part_id' => $part->id, 'quantity' => 15],
        ], $admin->id);

        $centralStock = WarehouseStock::where('warehouse_id', $central->id)->where('part_id', $part->id)->first();
        $mobileStock = WarehouseStock::where('warehouse_id', $mobile->id)->where('part_id', $part->id)->first();

        $this->assertEqualsWithDelta(85.0, (float) $centralStock->current_quantity, 0.01);
        $this->assertEqualsWithDelta(15.0, (float) $mobileStock->current_quantity, 0.01);

        $this->assertSame(2, StockMovement::where('part_id', $part->id)->count());
        $this->assertSame(1, StockMovement::where('warehouse_id', $central->id)->where('movement_type', 'transfer_out')->count());
        $this->assertSame(1, StockMovement::where('warehouse_id', $mobile->id)->where('movement_type', 'transfer_in')->count());
    }

    public function test_transfer_fails_when_source_has_insufficient_stock(): void
    {
        [$tenant, $technician, $admin] = $this->makeTenantWithTechnician();
        [$central, $mobile] = $this->makeWarehouses($tenant, $technician);
        $part = $this->makePart($tenant);

        WarehouseStock::create(['warehouse_id' => $central->id, 'part_id' => $part->id, 'current_quantity' => 5]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Saldo insuficiente/');

        app(StockTransferService::class)->transferToMobile($central->id, $mobile->id, [
            ['part_id' => $part->id, 'quantity' => 10],
        ], $admin->id);
    }

    public function test_transfer_fails_when_destination_is_not_mobile(): void
    {
        [$tenant, $technician, $admin] = $this->makeTenantWithTechnician();
        [$central] = $this->makeWarehouses($tenant, $technician);
        $otherCentral = Warehouse::create(['tenant_id' => $tenant->id, 'name' => 'Outro Central', 'type' => Warehouse::TYPE_CENTRAL]);
        $part = $this->makePart($tenant);

        WarehouseStock::create(['warehouse_id' => $central->id, 'part_id' => $part->id, 'current_quantity' => 50]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/tipo Volante/');

        app(StockTransferService::class)->transferToMobile($central->id, $otherCentral->id, [
            ['part_id' => $part->id, 'quantity' => 10],
        ], $admin->id);
    }

    public function test_consume_part_debits_technician_mobile_warehouse_and_updates_order_cost(): void
    {
        [$tenant, $technician] = $this->makeTenantWithTechnician();
        [, $mobile] = $this->makeWarehouses($tenant, $technician);
        $part = $this->makePart($tenant, 42.50);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Empilhadeira', 'status' => Asset::STATUS_LOCADO]);

        WarehouseStock::create(['warehouse_id' => $mobile->id, 'part_id' => $part->id, 'current_quantity' => 10]);

        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'os_number' => 'OS-'.uniqid(),
            'status' => 'Aberto', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
            'labor_cost' => 100, 'logistics_cost' => 20,
        ]);

        $orderPart = app(StockTransferService::class)->consumePartInWorkOrder(
            $order->id, $part->id, 3, $technician->id
        );

        $this->assertEqualsWithDelta(42.50, (float) $orderPart->unit_price, 0.01);
        $this->assertEqualsWithDelta(3.0, (float) $orderPart->quantity, 0.01);

        $stock = WarehouseStock::where('warehouse_id', $mobile->id)->where('part_id', $part->id)->first();
        $this->assertEqualsWithDelta(7.0, (float) $stock->current_quantity, 0.01);

        $movement = StockMovement::where('work_order_id', $order->id)->first();
        $this->assertNotNull($movement);
        $this->assertSame('exit_work_order', $movement->movement_type);
        $this->assertSame($mobile->id, $movement->warehouse_id);

        // material_cost = Part (3 * 42.50 = 127.50) + Material (nenhum) = 127.50
        // total_order_cost = 127.50 + labor_cost(100) + logistics_cost(20) = 247.50
        $order->refresh();
        $this->assertEqualsWithDelta(127.50, (float) $order->material_cost, 0.01);
        $this->assertEqualsWithDelta(247.50, (float) $order->total_order_cost, 0.01);
    }

    public function test_consume_part_fails_when_technician_has_no_mobile_warehouse(): void
    {
        [$tenant, $technician] = $this->makeTenantWithTechnician();
        $part = $this->makePart($tenant);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador', 'status' => Asset::STATUS_LOCADO]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'os_number' => 'OS-'.uniqid(),
            'status' => 'Aberto', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/não tem um Almoxarifado Volante/');

        app(StockTransferService::class)->consumePartInWorkOrder($order->id, $part->id, 1, $technician->id);
    }

    public function test_consume_part_blocks_when_insufficient_stock_and_negative_not_allowed(): void
    {
        [$tenant, $technician] = $this->makeTenantWithTechnician();
        [, $mobile] = $this->makeWarehouses($tenant, $technician);
        $part = $this->makePart($tenant);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Compressor', 'status' => Asset::STATUS_LOCADO]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'os_number' => 'OS-'.uniqid(),
            'status' => 'Aberto', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        WarehouseStock::create(['warehouse_id' => $mobile->id, 'part_id' => $part->id, 'current_quantity' => 2]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Saldo insuficiente/');

        app(StockTransferService::class)->consumePartInWorkOrder($order->id, $part->id, 5, $technician->id, allowNegative: false);
    }

    public function test_consume_part_allows_negative_stock_and_alerts_admin(): void
    {
        [$tenant, $technician, $admin] = $this->makeTenantWithTechnician();
        [, $mobile] = $this->makeWarehouses($tenant, $technician);
        $part = $this->makePart($tenant);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Guindaste', 'status' => Asset::STATUS_LOCADO]);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'os_number' => 'OS-'.uniqid(),
            'status' => 'Aberto', 'maintenance_type' => MaintenanceOrder::TYPE_CORRECTIVE,
        ]);

        WarehouseStock::create(['warehouse_id' => $mobile->id, 'part_id' => $part->id, 'current_quantity' => 2]);

        $orderPart = app(StockTransferService::class)->consumePartInWorkOrder(
            $order->id, $part->id, 5, $technician->id, allowNegative: true
        );

        $this->assertNotNull($orderPart);

        $stock = WarehouseStock::where('warehouse_id', $mobile->id)->where('part_id', $part->id)->first();
        $this->assertEqualsWithDelta(-3.0, (float) $stock->current_quantity, 0.01);

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertStringContainsString('Saldo negativo', $admin->notifications()->first()->data['title'] ?? '');
    }
}
