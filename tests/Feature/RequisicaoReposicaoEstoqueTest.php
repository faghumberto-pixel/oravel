<?php

namespace Tests\Feature;

use App\Filament\Pages\RequisicaoReposicaoEstoque;
use App\Models\InternalUnit;
use App\Models\Material;
use App\Models\MaterialLocationStock;
use App\Models\MaterialRequest;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RequisicaoReposicaoEstoqueTest extends TestCase
{
    use RefreshDatabase;

    private function makeTenantAdmin(): array
    {
        $plan = Plan::create([
            'name' => 'Plano Reposicao '.uniqid(), 'price' => 100, 'base_price' => 100, 'level' => 1,
            'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => ['tabela_materials', 'tabela_material_requests', 'tabela_internal_units', 'tabela_material_location_stock'],
        ]);

        $tenant = Tenant::create([
            'name' => 'Tenant Reposicao '.uniqid(), 'slug' => 'tenant-reposicao-'.uniqid(),
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

    public function test_only_materials_below_minimum_appear_for_the_chosen_unit(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $unit = InternalUnit::create(['tenant_id' => $tenant->id, 'name' => 'Matriz', 'code' => 'MATRIZ', 'type' => 'matriz']);

        $low = Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-BAIXO', 'name' => 'Correia', 'unit_cost' => 10, 'current_stock' => 1, 'min_stock' => 5]);
        MaterialLocationStock::create(['tenant_id' => $tenant->id, 'material_id' => $low->id, 'internal_unit_id' => $unit->id, 'current_quantity' => 1, 'minimum_threshold' => 5, 'maximum_threshold' => 20]);

        $ok = Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-OK', 'name' => 'Filtro', 'unit_cost' => 10, 'current_stock' => 10, 'min_stock' => 5]);
        MaterialLocationStock::create(['tenant_id' => $tenant->id, 'material_id' => $ok->id, 'internal_unit_id' => $unit->id, 'current_quantity' => 10, 'minimum_threshold' => 5, 'maximum_threshold' => 20]);

        $component = Livewire::test(RequisicaoReposicaoEstoque::class)->set('internalUnitId', $unit->id);

        $rows = $component->instance()->lowStockRows;

        $this->assertTrue($rows->contains('material_id', $low->id));
        $this->assertFalse($rows->contains('material_id', $ok->id));
    }

    public function test_generating_the_request_creates_one_material_request_with_correct_items_and_origin(): void
    {
        [$tenant, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $unit = InternalUnit::create(['tenant_id' => $tenant->id, 'name' => 'Filial Sul', 'code' => 'FILIAL-SUL', 'type' => 'filial']);

        $low = Material::create(['tenant_id' => $tenant->id, 'sku' => 'SKU-BAIXO', 'name' => 'Correia', 'unit_cost' => 10, 'current_stock' => 1, 'min_stock' => 5]);
        MaterialLocationStock::create(['tenant_id' => $tenant->id, 'material_id' => $low->id, 'internal_unit_id' => $unit->id, 'current_quantity' => 1, 'minimum_threshold' => 5, 'maximum_threshold' => 20]);

        Livewire::test(RequisicaoReposicaoEstoque::class)
            ->set('internalUnitId', $unit->id)
            ->call('gerarRequisicao');

        $materialRequest = MaterialRequest::where('requested_for_location_id', $unit->id)->first();

        $this->assertNotNull($materialRequest);
        $this->assertSame(MaterialRequest::ORIGIN_REPOSICAO_ESTOQUE, $materialRequest->origin);
        $this->assertNull($materialRequest->maintenance_order_id);
        $this->assertSame(1, $materialRequest->items()->count());
        $this->assertSame(19, $materialRequest->items()->first()->quantity);
    }

    public function test_page_renders_without_errors(): void
    {
        [, $admin] = $this->makeTenantAdmin();
        $this->actingAs($admin);

        $this->get(RequisicaoReposicaoEstoque::getUrl())->assertSuccessful();
    }
}
