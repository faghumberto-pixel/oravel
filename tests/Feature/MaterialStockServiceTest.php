<?php

namespace Tests\Feature;

use App\Models\InternalUnit;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MaterialStockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regressao: MaterialStockService chamava App\Models\StockMovement::record()
 * -- metodo/constantes inexistentes (a classe certa e' MaterialStockMovement,
 * ver docblock do proprio service). Todo receive()/consume()/transfer()/
 * adjust() lancava BadMethodCallException/Error em uso real (achado ao
 * popular dados demo de Compras, 2026-09-09). Fixed sem test antes -- este
 * arquivo cobre os 4 metodos pra nao regressar.
 */
class MaterialStockServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenant(): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plano Estoque '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_materials', 'tabela_material_stock_movements'],
        ]);

        return Tenant::create([
            'name' => 'Tenant Estoque '.uniqid(), 'slug' => 'tenant-estoque-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
    }

    private function makeMaterial(Tenant $tenant, int $currentStock = 10): Material
    {
        return Material::create([
            'tenant_id' => $tenant->id, 'name' => 'Parafuso Teste', 'sku' => 'SKU-'.uniqid(),
            'current_stock' => $currentStock, 'unit_cost' => 5,
        ]);
    }

    private function makeUnit(Tenant $tenant, string $name = 'Filial Teste'): InternalUnit
    {
        return InternalUnit::create(['tenant_id' => $tenant->id, 'name' => $name]);
    }

    public function test_receive_increments_stock_and_logs_entrada_movement(): void
    {
        $tenant = $this->makeTenant();
        $material = $this->makeMaterial($tenant, currentStock: 0);
        $unit = $this->makeUnit($tenant);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id, 'is_approved' => true,
        ]);

        $stock = app(MaterialStockService::class)->receive($material, $unit, 10, userId: $admin->id);

        $this->assertSame(10, $stock->current_quantity);
        $this->assertSame(10, $material->fresh()->current_stock);
        $movement = MaterialStockMovement::where('material_id', $material->id)->sole();
        $this->assertSame(MaterialStockMovement::TYPE_ENTRADA, $movement->type);
        $this->assertEquals(10, $movement->quantity);
    }

    public function test_consume_decrements_stock_and_logs_saida_movement(): void
    {
        $tenant = $this->makeTenant();
        $material = $this->makeMaterial($tenant, currentStock: 0);
        $unit = $this->makeUnit($tenant);
        app(MaterialStockService::class)->receive($material, $unit, 20);

        $stock = app(MaterialStockService::class)->consume($material, $unit, 8);

        $this->assertSame(12, $stock->current_quantity);
        $this->assertSame(12, $material->fresh()->current_stock);
        $this->assertSame(1, MaterialStockMovement::where('material_id', $material->id)->where('type', MaterialStockMovement::TYPE_SAIDA)->count());
    }

    public function test_transfer_moves_stock_between_units_and_logs_both_sides(): void
    {
        $tenant = $this->makeTenant();
        $material = $this->makeMaterial($tenant, currentStock: 0);
        $unitA = $this->makeUnit($tenant, 'Filial A');
        $unitB = $this->makeUnit($tenant, 'Filial B');
        app(MaterialStockService::class)->receive($material, $unitA, 15);

        app(MaterialStockService::class)->transfer($material, $unitA, $unitB, 6);

        $this->assertSame(9, $material->fresh()->locationStocks()->where('internal_unit_id', $unitA->id)->first()->current_quantity);
        $this->assertSame(6, $material->fresh()->locationStocks()->where('internal_unit_id', $unitB->id)->first()->current_quantity);
        $types = MaterialStockMovement::where('material_id', $material->id)->pluck('type')->all();
        $this->assertContains(MaterialStockMovement::TYPE_SAIDA, $types);
        $this->assertContains(MaterialStockMovement::TYPE_ENTRADA, $types);
    }

    public function test_adjust_sets_absolute_quantity_and_logs_ajuste_movement(): void
    {
        $tenant = $this->makeTenant();
        $material = $this->makeMaterial($tenant, currentStock: 0);
        $unit = $this->makeUnit($tenant);
        app(MaterialStockService::class)->receive($material, $unit, 10);

        $stock = app(MaterialStockService::class)->adjust($material, $unit, 7);

        $this->assertSame(7, $stock->current_quantity);
        $this->assertSame(1, MaterialStockMovement::where('material_id', $material->id)->where('type', MaterialStockMovement::TYPE_AJUSTE)->count());
    }
}
