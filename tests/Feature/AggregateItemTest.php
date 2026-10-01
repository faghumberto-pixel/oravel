<?php

namespace Tests\Feature;

use App\Filament\Resources\AggregateItemResource\Pages\CreateAggregateItem;
use App\Filament\Resources\AggregateItemResource\Pages\ListAggregateItems;
use App\Models\AggregateItem;
use App\Models\AggregateItemEntry;
use App\Models\AggregateItemExit;
use App\Models\AggregateItemType;
use App\Models\Asset;
use App\Models\EpiEntry;
use App\Models\InternalUnit;
use App\Models\Material;
use App\Models\MaterialLocationStock;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Itens Agregados (bandeja de contencao, cabos, mangueiras...): controle
 * proprio, separado de Asset e de Material/Part.
 */
class AggregateItemTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Agregados '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_aggregate_items', 'tabela_aggregate_item_types'],
        ]);
        $tenant = Tenant::create([
            'name' => 'Tenant Agregados '.uniqid(), 'slug' => 'tenant-agregados-'.uniqid(),
            'plan_id' => $plan->id, 'status' => 'active',
        ]);
        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@oravel.com.br',
            'password' => bcrypt('teste123'), 'tenant_id' => $tenant->id,
            'email_verified_at' => now(), 'is_approved' => true,
        ]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    public function test_due_date_defaults_from_type_inspection_interval(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $type = AggregateItemType::create(['name' => 'Mangueira', 'inspection_interval_days' => 90]);
        $item = AggregateItem::create(['aggregate_item_type_id' => $type->id, 'code' => 'MG-001']);

        $this->assertSame($tenant->id, $item->tenant_id);
        $this->assertSame(now()->addDays(90)->toDateString(), $item->next_inspection_date->toDateString());
    }

    public function test_items_are_isolated_per_tenant(): void
    {
        [, $adminA] = $this->makeTenantAdmin();
        [, $adminB] = $this->makeTenantAdmin();

        $this->actingAs($adminA);
        $type = AggregateItemType::create(['name' => 'Cabo']);
        AggregateItem::create(['aggregate_item_type_id' => $type->id, 'code' => 'CB-001']);

        $this->actingAs($adminB);
        $this->assertSame(0, AggregateItem::count());
    }

    public function test_list_and_create_pages_work(): void
    {
        [, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);
        $type = AggregateItemType::create(['name' => 'Bandeja de Contenção']);

        Livewire::test(CreateAggregateItem::class)
            ->fillForm(['aggregate_item_type_id' => $type->id, 'code' => 'BD-001', 'status' => 'disponivel', 'condition' => 'bom'])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::test(ListAggregateItems::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(AggregateItem::all());
    }

    public function test_balance_is_entries_minus_exits_plus_ok_returns(): void
    {
        [, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $type = AggregateItemType::create(['name' => 'Mangueira']);
        $entry = AggregateItemEntry::create([
            'aggregate_item_type_id' => $type->id, 'entry_date' => now(), 'unit_price' => 25.5, 'quantity' => 10,
        ]);
        $this->assertSame('255.00', $entry->total);

        $asset = Asset::create(['name' => 'Gerador 1', 'patrimonio' => 'G-1', 'status' => Asset::STATUS_DISPONIVEL]);
        $exit = AggregateItemExit::create([
            'aggregate_item_type_id' => $type->id, 'asset_id' => $asset->id, 'exit_date' => now(),
            'quantity' => 4, 'reason' => AggregateItemExit::REASON_LOCACAO,
        ]);
        $this->assertSame(6, $type->balance());

        $exit->registerReturn(AggregateItemExit::CONDITION_OK);
        $this->assertSame(10, $type->balance());

        $exit2 = AggregateItemExit::create([
            'aggregate_item_type_id' => $type->id, 'asset_id' => $asset->id, 'exit_date' => now(),
            'quantity' => 2, 'reason' => AggregateItemExit::REASON_REPOSICAO,
        ]);
        $exit2->registerReturn(AggregateItemExit::CONDITION_NOK);
        $this->assertSame(8, $type->balance());
    }

    public function test_exit_of_a_specific_unit_links_it_to_the_asset_and_return_frees_it(): void
    {
        [, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $type = AggregateItemType::create(['name' => 'Bandeja']);
        $unit = AggregateItem::create(['aggregate_item_type_id' => $type->id, 'code' => 'BD-9']);
        $asset = Asset::create(['name' => 'Gerador 2', 'patrimonio' => 'G-2', 'status' => Asset::STATUS_DISPONIVEL]);

        $exit = AggregateItemExit::create([
            'aggregate_item_type_id' => $type->id, 'aggregate_item_id' => $unit->id, 'asset_id' => $asset->id,
            'exit_date' => now(), 'quantity' => 1, 'reason' => AggregateItemExit::REASON_LOCACAO,
        ]);
        $unit->refresh();
        $this->assertSame(AggregateItem::STATUS_LOCADO, $unit->status);
        $this->assertSame($asset->id, $unit->asset_id);

        $exit->registerReturn(AggregateItemExit::CONDITION_NOK);
        $unit->refresh();
        $this->assertSame(AggregateItem::STATUS_MANUTENCAO, $unit->status);
        $this->assertNull($unit->asset_id);
    }

    public function test_epi_entry_credits_material_stock_in_the_branch(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $material = Material::create(['tenant_id' => $tenant->id, 'name' => 'Luva', 'sku' => 'EPI-L1', 'unit_cost' => 10]);
        $unit = InternalUnit::create(['tenant_id' => $tenant->id, 'name' => 'Matriz']);

        $entry = EpiEntry::create([
            'material_id' => $material->id, 'internal_unit_id' => $unit->id,
            'entry_date' => now(), 'unit_price' => 10, 'quantity' => 30,
        ]);

        $this->assertSame('300.00', $entry->total);
        $this->assertEquals(30, MaterialLocationStock::where('material_id', $material->id)->where('internal_unit_id', $unit->id)->value('current_quantity'));
    }
}
