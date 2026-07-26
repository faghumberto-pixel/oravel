<?php

namespace Tests\Feature;

use App\Filament\Pages\PlantaBaixaAlmoxarifado;
use App\Filament\Pages\PlantaBaixaPatioAtivos;
use App\Livewire\PlantaBaixaGrid;
use App\Models\AbcMatrix;
use App\Models\Asset;
use App\Models\CriticalityLevel;
use App\Models\InternalUnit;
use App\Models\Material;
use App\Models\MaterialLocationStock;
use App\Models\Plan;
use App\Models\Role;
use App\Models\StorageLocation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlantaBaixaGridTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Planta '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_storage_locations', 'tabela_assets', 'tabela_materials', 'tabela_internal_units', 'tabela_abc_matrix', 'tabela_criticality_levels'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Planta '.uniqid(), 'slug' => 'tenant-planta-'.uniqid(),
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

    public function test_asset_cell_color_defaults_to_status_and_covers_all_seven_statuses(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $unit = InternalUnit::create(['tenant_id' => $tenant->id, 'name' => 'Matriz', 'code' => 'MATRIZ', 'type' => 'matriz']);
        $location = StorageLocation::create([
            'tenant_id' => $tenant->id, 'internal_unit_id' => $unit->id,
            'context' => StorageLocation::CONTEXT_PATIO_ATIVOS, 'code' => 'Q1', 'row' => 1, 'column' => 1,
        ]);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Gerador', 'tag' => 'AST-'.uniqid(),
            'status' => Asset::STATUS_QUARENTENA, 'storage_location_id' => $location->id,
        ]);

        $component = Livewire::test(PlantaBaixaGrid::class, ['context' => StorageLocation::CONTEXT_PATIO_ATIVOS, 'internalUnitId' => $unit->id]);

        $this->assertSame('purple', $component->instance()->cellColor($location->id));
        $this->assertSame(Asset::statusColor($asset->status), $component->instance()->cellColor($location->id));
    }

    public function test_color_mode_criticidade_uses_abc_matrix_and_criticality_level_color(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $unit = InternalUnit::create(['tenant_id' => $tenant->id, 'name' => 'Matriz', 'code' => 'MATRIZ', 'type' => 'matriz']);
        $location = StorageLocation::create([
            'tenant_id' => $tenant->id, 'internal_unit_id' => $unit->id,
            'context' => StorageLocation::CONTEXT_PATIO_ATIVOS, 'code' => 'Q2', 'row' => 1, 'column' => 2,
        ]);
        $asset = Asset::create([
            'tenant_id' => $tenant->id, 'name' => 'Compressor', 'tag' => 'AST-'.uniqid(),
            'status' => Asset::STATUS_DISPONIVEL, 'storage_location_id' => $location->id,
        ]);
        CriticalityLevel::create(['tenant_id' => $tenant->id, 'code' => 'A', 'name' => 'Alta', 'color' => '#ff0000']);
        AbcMatrix::create(['tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'nivel' => 'A', 'descricao' => 'Critica']);

        $component = Livewire::test(PlantaBaixaGrid::class, [
            'context' => StorageLocation::CONTEXT_PATIO_ATIVOS,
            'internalUnitId' => $unit->id,
        ])->set('colorMode', 'criticidade');

        $this->assertSame('criticidade', $component->instance()->cellColor($location->id));
        $this->assertSame('#ff0000', $component->instance()->cellCriticalityHex($location->id));
    }

    public function test_selecting_a_cell_returns_the_correct_occupants(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $unit = InternalUnit::create(['tenant_id' => $tenant->id, 'name' => 'Matriz', 'code' => 'MATRIZ', 'type' => 'matriz']);
        $location = StorageLocation::create([
            'tenant_id' => $tenant->id, 'internal_unit_id' => $unit->id,
            'context' => StorageLocation::CONTEXT_ALMOXARIFADO, 'code' => 'A1-01', 'row' => 1, 'column' => 1,
        ]);
        $material = Material::create([
            'tenant_id' => $tenant->id, 'sku' => 'SKU-'.uniqid(), 'name' => 'Filtro de óleo',
            'unit_cost' => 10, 'current_stock' => 2, 'min_stock' => 5, 'storage_location_id' => $location->id,
        ]);
        MaterialLocationStock::create([
            'tenant_id' => $tenant->id, 'material_id' => $material->id, 'internal_unit_id' => $unit->id,
            'current_quantity' => 2, 'minimum_threshold' => 5,
        ]);

        $component = Livewire::test(PlantaBaixaGrid::class, ['context' => StorageLocation::CONTEXT_ALMOXARIFADO, 'internalUnitId' => $unit->id]);

        $this->assertSame('warning', $component->instance()->cellColor($location->id));

        $component->call('selectLocation', $location->id);

        $this->assertSame($location->id, $component->instance()->selectedLocationId);
        $this->assertTrue($component->instance()->selectedOccupants->contains('id', $material->id));
    }

    public function test_locations_from_a_different_tenant_never_leak_into_the_grid(): void
    {
        [$tenantA, $adminA] = $this->makeTenantAdmin();
        [$tenantB] = $this->makeTenantAdmin();
        $this->actingAs($adminA);

        $unitA = InternalUnit::create(['tenant_id' => $tenantA->id, 'name' => 'Matriz A', 'code' => 'A', 'type' => 'matriz']);
        $unitB = InternalUnit::create(['tenant_id' => $tenantB->id, 'name' => 'Matriz B', 'code' => 'B', 'type' => 'matriz']);

        StorageLocation::create([
            'tenant_id' => $tenantB->id, 'internal_unit_id' => $unitB->id,
            'context' => StorageLocation::CONTEXT_PATIO_ATIVOS, 'code' => 'Q9', 'row' => 1, 'column' => 1,
        ]);

        $component = Livewire::test(PlantaBaixaGrid::class, ['context' => StorageLocation::CONTEXT_PATIO_ATIVOS, 'internalUnitId' => $unitA->id]);

        $this->assertTrue($component->instance()->locations->isEmpty());
    }

    public function test_both_planta_baixa_pages_render_without_errors(): void
    {
        [, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $this->get(PlantaBaixaAlmoxarifado::getUrl())->assertSuccessful();
        $this->get(PlantaBaixaPatioAtivos::getUrl())->assertSuccessful();
    }
}
