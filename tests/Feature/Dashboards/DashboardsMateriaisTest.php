<?php

namespace Tests\Feature\Dashboards;

use App\Dashboards\Blocks\MateriaisPecas\AbaixoDoMinimoSugestao;
use App\Dashboards\Blocks\MateriaisPecas\CoberturaEmDias;
use App\Dashboards\Blocks\MateriaisPecas\ComprasAReceber;
use App\Dashboards\Blocks\MateriaisPecas\ItensAbaixoDoMinimo;
use App\Dashboards\Blocks\MateriaisPecas\ValorEmEstoque;
use App\Dashboards\DashboardRenderer;
use App\Filament\Pages\VisaoGeralMateriais;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\Plan;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardsMateriaisTest extends TestCase
{
    use DatabaseTransactions;

    private const FEATURES = ['tabela_materials' => true, 'tabela_purchase_orders' => true, 'tabela_material_requests' => true, 'tabela_quotes' => true];

    protected function setUp(): void
    {
        parent::setUp();
        config(['oravel.dashboards_modulo' => true]);
        Cache::flush();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    private function empresa(array $features = self::FEATURES): array
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => $features]);
        $tenant = Tenant::create(['name' => 'E '.uniqid(), 'slug' => 'm-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
        $admin = User::create(['name' => 'Admin', 'email' => uniqid().'@oravel.test', 'password' => bcrypt('x'), 'tenant_id' => $tenant->id, 'is_approved' => true]);
        $admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $tenant->id]));

        return [$tenant, $admin];
    }

    private function material(Tenant $t, array $attrs = []): Material
    {
        return Material::create($attrs + ['tenant_id' => $t->id, 'sku' => 'SKU-'.uniqid(), 'name' => 'Material '.uniqid(), 'unit_cost' => 10, 'current_stock' => 5, 'min_stock' => 2, 'max_stock' => 20]);
    }

    public function test_sem_o_modulo_materiais_a_pagina_nao_existe(): void
    {
        [, $admin] = $this->empresa(['tabela_assets' => true]);
        $this->actingAs($admin);

        $this->assertFalse(VisaoGeralMateriais::canAccess());
        $this->assertSame([], DashboardRenderer::blocks('materiais', $admin));
    }

    public function test_so_os_blocos_dos_modulos_contratados_aparecem(): void
    {
        [, $admin] = $this->empresa(['tabela_materials' => true]); // sem compras, requisições e orçamentos
        $this->actingAs($admin);

        $chaves = array_column(DashboardRenderer::blocks('materiais', $admin), 'key');

        $this->assertContains('materiais.valor_em_estoque', $chaves);
        $this->assertNotContains('materiais.compras_a_receber', $chaves);
        $this->assertNotContains('materiais.requisicoes_abertas', $chaves);
    }

    public function test_valor_em_estoque_so_soma_o_proprio_tenant(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b] = $this->empresa();
        $this->material($a, ['unit_cost' => 100, 'current_stock' => 10]);
        $this->material($b, ['unit_cost' => 100, 'current_stock' => 1000]);
        $this->actingAs($adminA);

        $this->assertStringContainsString('1,0 mil', (new ValorEmEstoque)->data([])['value']);
    }

    public function test_abaixo_do_minimo_conta_e_separa_os_sem_pedido_aberto(): void
    {
        [$t, $admin] = $this->empresa();
        $this->material($t, ['current_stock' => 0, 'min_stock' => 5]);
        $comPedido = $this->material($t, ['current_stock' => 1, 'min_stock' => 5]);
        $this->material($t, ['current_stock' => 9, 'min_stock' => 5]); // acima do mínimo
        $fornecedor = Supplier::create(['tenant_id' => $t->id, 'name' => 'Forn']);
        $po = PurchaseOrder::create(['tenant_id' => $t->id, 'supplier_id' => $fornecedor->id, 'created_by_user_id' => $admin->id, 'status' => 'aberta']);
        $po->items()->create(['tenant_id' => $t->id, 'material_id' => $comPedido->id, 'quantity' => 10, 'unit_price' => 1]);
        $this->actingAs($admin);

        $p = (new ItensAbaixoDoMinimo)->data([]);

        $this->assertSame(2, $p['value']);
        $this->assertStringContainsString('1 sem pedido aberto', $p['delta']);
    }

    public function test_lista_abaixo_do_minimo_sugere_ate_o_maximo_e_marca_zerado(): void
    {
        [$t, $admin] = $this->empresa();
        $this->material($t, ['name' => 'Filtro', 'current_stock' => 0, 'min_stock' => 4, 'max_stock' => 12]);
        $this->actingAs($admin);

        $linha = (new AbaixoDoMinimoSugestao)->data([])['rows'][0];

        $this->assertSame('Filtro', $linha[0]);
        $this->assertSame('12', $linha[4]);
        $this->assertSame('Zerado', $linha[5]['text']);
    }

    public function test_compras_a_receber_ignora_pedidos_recebidos_e_de_outro_tenant(): void
    {
        [$a, $adminA] = $this->empresa();
        [$b] = $this->empresa();
        foreach ([[$a, 'aberta', 100], [$a, 'recebida', 900], [$b, 'aberta', 5000]] as [$t, $status, $valor]) {
            $f = Supplier::create(['tenant_id' => $t->id, 'name' => 'F']);
            PurchaseOrder::create(['tenant_id' => $t->id, 'supplier_id' => $f->id, 'created_by_user_id' => $adminA->id, 'status' => $status, 'total_value' => $valor]);
        }
        $this->actingAs($adminA);

        $p = (new ComprasAReceber)->data([]);

        $this->assertSame(1, $p['value']);
        $this->assertStringContainsString('100,00', $p['delta']);
    }

    public function test_cobertura_em_dias_usa_o_consumo_dos_ultimos_90_dias(): void
    {
        [$t, $admin] = $this->empresa();
        $m = $this->material($t, ['name' => 'Óleo', 'current_stock' => 30]);
        MaterialStockMovement::create(['tenant_id' => $t->id, 'material_id' => $m->id, 'type' => 'saida', 'quantity' => 90, 'balance_after' => 30]);
        $this->actingAs($admin);

        $linha = (new CoberturaEmDias)->data([])['rows'][0];

        $this->assertSame('Óleo', $linha[0]);
        $this->assertSame('30 dias', $linha[2]); // 30 de saldo ÷ (90 de saída / 90 dias)
        $this->assertSame('ok', $linha[3]);
    }

    public function test_a_pagina_renderiza(): void
    {
        [$t, $admin] = $this->empresa();
        $this->material($t, ['current_stock' => 0, 'min_stock' => 3]);
        $this->actingAs($admin);

        Livewire::test(VisaoGeralMateriais::class)
            ->assertSee('Valor em estoque')
            ->assertSee('Abaixo do mínimo')
            ->assertSee('Cobertura de materiais')
            ->assertSee('Zerado');
    }
}
