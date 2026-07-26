<?php

namespace Tests\Feature;

use App\Filament\Resources\MaterialRequestResource\Pages\CreateMaterialRequest;
use App\Filament\Resources\MaterialRequestResource\Pages\EditMaterialRequest;
use App\Filament\Resources\PartsRequestResource;
use App\Models\Asset;
use App\Models\InternalUnit;
use App\Models\MaintenanceOrder;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\PartsRequest;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaterialRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano MR '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_materials', 'tabela_material_requests', 'tabela_internal_units', 'tabela_parts_requests', 'tabela_maintenance_orders', 'tabela_assets'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant MR '.uniqid(), 'slug' => 'tenant-mr-'.uniqid(),
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

    public function test_creating_a_material_request_with_items_repeater_creates_request_and_items_in_one_submit(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $material = Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-1', 'name' => 'Correia', 'unit_cost' => 10]);

        Livewire::test(CreateMaterialRequest::class)
            ->fillForm([
                'user_id' => $admin->id,
                'priority' => MaterialRequest::PRIORITY_NORMAL,
                'status' => MaterialRequest::STATUS_RASCUNHO,
                'items' => [
                    ['material_id' => $material->id, 'quantity' => 3],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $materialRequest = MaterialRequest::first();

        $this->assertNotNull($materialRequest);
        $this->assertSame(MaterialRequest::ORIGIN_MANUAL, $materialRequest->origin);
        $this->assertSame(1, $materialRequest->items()->count());
        $this->assertSame(3, $materialRequest->items()->first()->quantity);
    }

    public function test_editing_an_existing_material_request_still_works_through_the_relation_manager_data(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $material = Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-2', 'name' => 'Filtro', 'unit_cost' => 10]);
        $materialRequest = MaterialRequest::create([
            'tenant_id' => $tenant->id, 'user_id' => $admin->id, 'status' => MaterialRequest::STATUS_RASCUNHO,
        ]);
        $materialRequest->items()->create(['material_id' => $material->id, 'quantity' => 2]);

        Livewire::test(EditMaterialRequest::class, ['record' => $materialRequest->id])
            ->assertFormSet(['priority' => MaterialRequest::PRIORITY_NORMAL])
            ->assertSuccessful();
    }

    public function test_converting_a_parts_request_creates_material_request_with_conversao_origin(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $unit = InternalUnit::create(['tenant_id' => $tenant->id, 'name' => 'Matriz', 'code' => 'MATRIZ', 'type' => 'matriz']);
        $material = Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-3', 'name' => 'Rolamento', 'unit_cost' => 10]);
        $asset = Asset::create(['tenant_id' => $tenant->id, 'name' => 'Gerador', 'tag' => 'AST-'.uniqid(), 'status' => 'disponivel']);
        $order = MaintenanceOrder::create([
            'tenant_id' => $tenant->id, 'asset_id' => $asset->id, 'technician_id' => $admin->id,
            'description' => 'OS teste', 'maintenance_type' => MaintenanceOrder::TYPE_PREVENTIVE,
        ]);
        $partsRequest = PartsRequest::create([
            'tenant_id' => $tenant->id, 'maintenance_order_id' => $order->id, 'material_id' => $material->id,
            'quantity' => 2, 'status' => 'pendente',
        ]);

        Livewire::test(PartsRequestResource\Pages\ManagePartsRequests::class)
            ->callTableAction('converter_em_requisicao', $partsRequest);

        $partsRequest->refresh();
        $this->assertNotNull($partsRequest->converted_to_material_request_id);

        $materialRequest = MaterialRequest::find($partsRequest->converted_to_material_request_id);
        $this->assertSame(MaterialRequest::ORIGIN_CONVERSAO_PARTS_REQUEST, $materialRequest->origin);
    }
}
