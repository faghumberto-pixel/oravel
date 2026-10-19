<?php

namespace Tests\Feature;

use App\Filament\Resources\MaterialRequestResource\Pages\ListMaterialRequests;
use App\Filament\Resources\MaterialResource\Pages\CreateMaterial;
use App\Filament\Resources\MaterialResource\Pages\ListMaterials;
use App\Filament\Resources\WarehouseResource\Pages\CreateWarehouse;
use App\Filament\Resources\WarehouseResource\Pages\ListWarehouses;
use App\Models\Material;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\TenantProvisioner;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Estoque na interface simples: cadastros e listas curtos, sem exclusão; Locadora segue com o completo. */
class EstoqueSimplesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function admin(string $pacote): User
    {
        $plano = Pacotes::planoDeVitrine($pacote);
        $plano->update(['is_active' => true, 'features' => array_fill_keys(Pacotes::features($pacote), true)]);
        $t = Tenant::factory()->create(['plan_id' => $plano->id]);
        $u = TenantProvisioner::provision($t, ['name' => 'Maria', 'email' => 'm-'.uniqid().'@oravel.test', 'password' => 'senha12345']);
        $u->forceFill(['is_approved' => true])->save();

        return $u;
    }

    public function test_material_simples_pede_so_o_basico_e_grava_nas_mesmas_colunas(): void
    {
        $u = $this->admin('estoque');
        $this->actingAs($u);

        Livewire::test(CreateMaterial::class)
            ->assertFormFieldExists('name')->assertFormFieldExists('unit_cost')->assertFormFieldExists('min_stock')
            ->assertFormFieldDoesNotExist('part_number')->assertFormFieldDoesNotExist('ncm')->assertFormFieldDoesNotExist('is_epi')
            ->fillForm(['name' => 'Filtro de óleo', 'sku' => 'FIL-001', 'unit_of_measure' => 'un', 'unit_cost' => 35.5, 'min_stock' => 4, 'warehouse_location' => 'Prateleira B'])
            ->call('create')->assertHasNoFormErrors();

        $m = Material::where('sku', 'FIL-001')->firstOrFail();
        $this->assertSame($u->tenant_id, $m->tenant_id);
        $this->assertEquals(35.5, (float) $m->unit_cost);
        $this->assertEquals(0, (float) $m->price, 'preço de venda segue com padrão 0');
        $this->assertSame('Prateleira B', $m->warehouse_location);
    }

    public function test_almoxarifado_simples_cria_com_nome_e_endereco(): void
    {
        $u = $this->admin('estoque');
        $this->actingAs($u);

        Livewire::test(CreateWarehouse::class)->assertFormFieldDoesNotExist('vehicle_plate')
            ->fillForm(['name' => 'Depósito central', 'city' => 'Campinas', 'is_active' => true])->call('create')->assertHasNoFormErrors();

        $w = Warehouse::where('name', 'Depósito central')->firstOrFail();
        $this->assertSame($u->tenant_id, $w->tenant_id);
        $this->assertSame(Warehouse::TYPE_CENTRAL, $w->type);
    }

    public function test_listas_simples_escondem_colunas_tecnicas_e_a_locadora_mantem_tudo(): void
    {
        $this->actingAs($this->admin('estoque'));
        Livewire::test(ListMaterials::class)->assertSuccessful()->assertTableColumnExists('name')->assertTableColumnHidden('brand_name')->assertTableColumnHidden('price');
        Livewire::test(ListWarehouses::class)->assertSuccessful()->assertTableColumnHidden('technician.name')->assertTableColumnHidden('manager.name');
        Livewire::test(ListMaterialRequests::class)->assertSuccessful()->assertTableColumnHidden('origin');

        $this->actingAs($this->admin('locadora'));
        Livewire::test(ListMaterials::class)->assertTableColumnVisible('brand_name');
        Livewire::test(CreateMaterial::class)->assertFormFieldExists('part_number');
        Livewire::test(CreateWarehouse::class)->assertFormFieldExists('vehicle_plate');
    }
}
