<?php

namespace Tests\Feature;

use App\Filament\Resources\AggregateItemResource\Pages\CreateAggregateItem;
use App\Filament\Resources\AggregateItemResource\Pages\ListAggregateItems;
use App\Models\AggregateItem;
use App\Models\AggregateItemType;
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
}
