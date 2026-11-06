<?php

namespace Tests\Feature;

use App\Models\AggregateItem;
use App\Models\AggregateItemType;
use App\Models\Asset;
use App\Models\FrotaTrocaOleo;
use App\Models\InternalUnit;
use App\Models\Material;
use App\Models\MaterialLocationStock;
use App\Models\MaterialStockMovement;
use App\Models\Part;
use App\Models\PartCategory;
use App\Models\Plan;
use App\Models\Role;
use App\Models\StockCount;
use App\Models\StockGroup;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\StockPlacement;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseStock;
use App\Services\Estoque\ContagemFisica;
use App\Services\Estoque\EstoqueGrupos;
use App\Services\Estoque\EstoqueUnificado;
use App\Services\Estoque\RastreioDoItem;
use App\Services\MaterialStockService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Estoque estruturado: grupos, enderecos (corredor > prateleira > nivel), visao unica dos 3 cadastros e contagem fisica. */
class EstoqueEstruturadoTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $admin;

    private InternalUnit $unidade;

    private function empresa(): void
    {
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_materials' => true, 'tabela_stock_structure' => true]]);
        $this->tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'es-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $this->admin = User::create(['name' => 'A', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $this->admin->forceFill(['email_verified_at' => now()])->save();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
        $this->unidade = InternalUnit::unguarded(fn () => InternalUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Matriz', 'code' => 'MTZ', 'type' => 'matriz', 'is_active' => true]));
        $this->actingAs($this->admin);
    }

    private function endereco(string $corredor, string $prateleira, ?string $nivel = null): StockLocation
    {
        return StockLocation::create(['tenant_id' => $this->tenant->id, 'internal_unit_id' => $this->unidade->id, 'aisle' => $corredor, 'shelf' => $prateleira, 'level' => $nivel]);
    }

    private function material(string $nome, int $saldo, int $minimo, ?StockGroup $grupo = null): Material
    {
        return Material::unguarded(fn () => Material::create(['tenant_id' => $this->tenant->id, 'sku' => 'M-'.uniqid(), 'name' => $nome, 'unit_cost' => 10, 'min_stock' => $minimo, 'max_stock' => 100, 'current_stock' => $saldo, 'stock_group_id' => $grupo?->id]));
    }

    private function colocar(StockLocation $e, string $tipo, $item, float $qtd): StockPlacement
    {
        return StockPlacement::create(['tenant_id' => $this->tenant->id, 'stock_location_id' => $e->id, 'item_type' => $tipo, 'item_id' => (string) $item->getKey(), 'quantity' => $qtd]);
    }

    public function test_endereco_monta_o_codigo_com_a_hierarquia(): void
    {
        $this->empresa();

        $e = $this->endereco('A', '03', '2');

        $this->assertSame('C-A · P-03 · N-2', $e->code);
        $this->assertSame('C-B · P-01', $this->endereco('B', '01')->code);
    }

    public function test_grupos_padrao_sao_criados_uma_vez_por_empresa(): void
    {
        $this->empresa();

        $this->assertCount(6, app(EstoqueGrupos::class)->garantirPadrao((string) $this->tenant->id));
        $this->assertCount(6, app(EstoqueGrupos::class)->garantirPadrao((string) $this->tenant->id));
        $this->assertSame(['Administração e escritório', 'Equipamentos', 'Veículos', 'Manutenção', 'Logística', 'Outros'], StockGroup::orderBy('sort_order')->pluck('name')->all());
    }

    public function test_visao_unica_junta_materiais_pecas_e_itens_agregados_com_filtros(): void
    {
        $this->empresa();
        $grupos = app(EstoqueGrupos::class)->garantirPadrao((string) $this->tenant->id)->keyBy('name');
        $adm = $grupos['Administração e escritório'];
        $veic = $grupos['Veículos'];

        $papel = $this->material('Papel A4', 3, 10, $adm); // abaixo do minimo
        $cabo = $this->material('Cabo de aço', 50, 5, $grupos['Equipamentos']);
        $categoria = PartCategory::create(['tenant_id' => $this->tenant->id, 'name' => 'Lubrificantes', 'slug' => 'lub']);
        $oleo = Part::create(['tenant_id' => $this->tenant->id, 'part_category_id' => $categoria->id, 'sku' => 'OL-1', 'name' => 'Óleo 15W40', 'unit_of_measure' => 'L', 'cost_price' => 20, 'minimum_stock' => 10, 'stock_group_id' => $veic->id]);
        $deposito = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Depósito', 'code' => 'D1', 'is_active' => true]);
        WarehouseStock::create(['warehouse_id' => $deposito->id, 'part_id' => $oleo->id, 'current_quantity' => 40, 'reserved_quantity' => 0]);
        $tipo = AggregateItemType::create(['tenant_id' => $this->tenant->id, 'name' => 'Cinta de içamento', 'category' => 'acessorio', 'unit_of_measure' => 'un', 'stock_group_id' => $grupos['Equipamentos']->id]);
        AggregateItem::unguarded(fn () => AggregateItem::create(['tenant_id' => $this->tenant->id, 'aggregate_item_type_id' => $tipo->id, 'code' => 'CI-1', 'status' => 'disponivel', 'purchase_value' => 100]));

        $a = $this->endereco('A', '01');
        $this->colocar($a, StockPlacement::MATERIAL, $papel, 3);
        $this->colocar($a, StockPlacement::PART, $oleo, 40);

        $servico = app(EstoqueUnificado::class);
        $todos = $servico->itens();

        $this->assertCount(4, $todos);
        $this->assertEqualsCanonicalizing(['Material', 'Material', 'Peça / óleo', 'Item agregado'], $todos->pluck('tipo_rotulo')->all());
        $this->assertSame(['Papel A4'], $servico->itens(['grupo' => (string) $adm->id])->pluck('nome')->all());
        $this->assertSame(['Óleo 15W40'], $servico->itens(['grupo' => (string) $veic->id])->pluck('nome')->all());
        $this->assertSame(['Papel A4'], $servico->itens(['situacao' => 'abaixo'])->pluck('nome')->all());
        $this->assertEqualsCanonicalizing(['Papel A4', 'Óleo 15W40'], $servico->itens(['corredor' => 'A'])->pluck('nome')->all());
        $this->assertEqualsCanonicalizing(['Cabo de aço', 'Cinta de içamento'], $servico->itens(['endereco' => 'sem'])->pluck('nome')->all());
        $this->assertSame(['Cabo de aço'], $servico->itens(['busca' => 'cabo'])->pluck('nome')->all());

        $resumo = $servico->resumo($todos);
        $this->assertSame(4, $resumo['itens']);
        $this->assertSame(1, $resumo['abaixo']);
        $this->assertSame(2, $resumo['sem_endereco']);
        $this->assertEqualsWithDelta(30 + 500 + 800 + 100, $resumo['valor'], 0.01);
        $oleoLinha = $todos->firstWhere('nome', 'Óleo 15W40');
        $this->assertSame(['C-A · P-01'], $oleoLinha['enderecos']);
    }

    public function test_contagem_monta_a_folha_pelo_escopo_e_finaliza_ajustando_o_estoque(): void
    {
        $this->empresa();
        $grupos = app(EstoqueGrupos::class)->garantirPadrao((string) $this->tenant->id)->keyBy('name');
        $papel = $this->material('Papel A4', 20, 5, $grupos['Administração e escritório']);
        $cabo = $this->material('Cabo de aço', 50, 5, $grupos['Equipamentos']);
        MaterialLocationStock::unguarded(fn () => MaterialLocationStock::create(['tenant_id' => $this->tenant->id, 'material_id' => $papel->id, 'internal_unit_id' => $this->unidade->id, 'current_quantity' => 20]));
        $categoria = PartCategory::create(['tenant_id' => $this->tenant->id, 'name' => 'Lub', 'slug' => 'lub']);
        $oleo = Part::create(['tenant_id' => $this->tenant->id, 'part_category_id' => $categoria->id, 'sku' => 'OL-1', 'name' => 'Óleo', 'unit_of_measure' => 'L', 'cost_price' => 20, 'minimum_stock' => 1, 'stock_group_id' => $grupos['Manutenção']->id]);
        $deposito = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Depósito', 'code' => 'D1', 'is_active' => true]);
        WarehouseStock::create(['warehouse_id' => $deposito->id, 'part_id' => $oleo->id, 'current_quantity' => 40, 'reserved_quantity' => 0]);

        $a = $this->endereco('A', '01');
        $b = $this->endereco('B', '01');
        $this->colocar($a, StockPlacement::MATERIAL, $papel, 20);
        $this->colocar($b, StockPlacement::MATERIAL, $cabo, 50);
        $this->colocar($a, StockPlacement::PART, $oleo, 40);

        $servico = app(ContagemFisica::class);

        // escopo: so' o corredor A => papel e oleo; o cabo (corredor B) fica de fora
        $contagem = $servico->criar((string) $this->tenant->id, (string) $this->unidade->id, 'Inventário corredor A', null, 'A', (string) $this->admin->id);
        $this->assertSame(['Papel A4', 'Óleo'], $contagem->items()->orderBy('item_name')->pluck('item_name')->all());

        // escopo por grupo
        $porGrupo = $servico->criar((string) $this->tenant->id, (string) $this->unidade->id, 'Só equipamentos', (string) $grupos['Equipamentos']->id);
        $this->assertSame(['Cabo de aço'], $porGrupo->items()->pluck('item_name')->all());

        // contou 19 papeis, recontou e deu 18 (faltam 2); contou 43 litros (sobram 3). Fica o historico de quem contou e quando.
        $papelItem = $contagem->items()->where('item_name', 'Papel A4')->first();
        $servico->registrar($papelItem, 19, $this->admin, 'celular', null, now()->subMinutes(5));
        $servico->registrar($papelItem->fresh(), 18, $this->admin, 'web', 'recontagem');
        $servico->registrar($contagem->items()->where('item_name', 'Óleo')->first(), 43, $this->admin, 'celular');

        $papelItem = $papelItem->fresh();
        $this->assertCount(2, $papelItem->entries);
        $this->assertEquals(18, $papelItem->counted_quantity);
        $this->assertSame($this->admin->name, $papelItem->counted_by_name);
        $this->assertSame('web', $papelItem->entries->first()->source); // o mais recente primeiro
        $this->assertSame('celular', $papelItem->entries->last()->source);
        $this->assertEquals([18.0, 19.0], $papelItem->entries->pluck('quantity')->map(fn ($q) => (float) $q)->all());

        $r = $servico->finalizar($contagem->fresh(), (string) $this->admin->id);

        $this->assertSame(['ajustados' => 2, 'divergentes' => 2, 'sem_contagem' => 0, 'informativos' => 0], $r);
        $this->assertSame(StockCount::FINALIZADA, $contagem->fresh()->status);
        $this->assertEquals(18, MaterialLocationStock::where('material_id', $papel->id)->value('current_quantity'));
        $this->assertEquals(43, WarehouseStock::where('part_id', $oleo->id)->value('current_quantity'));
        $this->assertEquals(18, StockPlacement::where('stock_location_id', $a->id)->where('item_id', (string) $papel->id)->value('quantity'));
        $this->assertDatabaseHas('stock_movements', ['part_id' => $oleo->id, 'movement_type' => 'entry_adjustment', 'quantity' => 3]);

        $this->expectException(\RuntimeException::class);
        $servico->finalizar($contagem->fresh());
    }

    public function test_rastreio_do_item_junta_movimentos_ajustes_e_contagens_de_cada_item(): void
    {
        $this->empresa();
        $papel = $this->material('Papel A4', 20, 5);
        $outro = $this->material('Caneta', 9, 1);
        foreach ([['entrada', 30, 30, 10], ['saida', 10, 20, 5], ['ajuste', -2, 18, 1]] as [$tipo, $q, $saldo, $dias]) {
            MaterialStockMovement::unguarded(fn () => MaterialStockMovement::create(['tenant_id' => $this->tenant->id, 'material_id' => $papel->id, 'type' => $tipo, 'quantity' => $q, 'balance_after' => $saldo, 'created_by_user_id' => $this->admin->id, 'reason' => 'teste '.$tipo, 'created_at' => now()->subDays($dias)]));
        }
        MaterialStockMovement::create(['tenant_id' => $this->tenant->id, 'material_id' => $outro->id, 'type' => 'entrada', 'quantity' => 9, 'balance_after' => 9, 'created_by_user_id' => $this->admin->id]);

        $a = $this->endereco('A', '01');
        $this->colocar($a, StockPlacement::MATERIAL, $papel, 20);
        $contagens = app(ContagemFisica::class);
        $c = $contagens->criar((string) $this->tenant->id, (string) $this->unidade->id, 'Inventário', null, null, (string) $this->admin->id);
        $contagens->registrar($c->items()->first(), 18, $this->admin, 'celular');

        $rastreio = app(RastreioDoItem::class);
        $eventos = $rastreio->eventos(StockPlacement::MATERIAL, (string) $papel->id);

        $this->assertSame(['contagem', 'ajuste', 'saida', 'entrada'], $eventos->pluck('tipo')->all()); // mais recente primeiro
        $this->assertSame($this->admin->name, $eventos->firstWhere('tipo', 'contagem')['quem']);
        $this->assertSame(18.0, $eventos->firstWhere('tipo', 'ajuste')['saldo']);
        $totais = $rastreio->totais($eventos);
        $this->assertSame(30.0, $totais['entradas']);
        $this->assertSame(10.0, $totais['saidas']);
        $this->assertSame(1, $totais['contagens']);

        // nao mistura com outro item
        $this->assertCount(1, $rastreio->eventos(StockPlacement::MATERIAL, (string) $outro->id));
        $this->assertSame('Papel A4', $rastreio->cabecalho(StockPlacement::MATERIAL, (string) $papel->id)['nome']);
        $this->assertArrayHasKey('material|'.$papel->id, $rastreio->buscar('papel'));
    }

    public function test_rastreio_do_oleo_junta_a_troca_da_frota_e_a_baixa_do_estoque_num_evento_so(): void
    {
        $this->empresa();
        $categoria = PartCategory::create(['tenant_id' => $this->tenant->id, 'name' => 'Óleos', 'slug' => 'oleos']);
        $oleo = Part::create(['tenant_id' => $this->tenant->id, 'part_category_id' => $categoria->id, 'sku' => 'OL-9', 'name' => 'Óleo 15W40', 'unit_of_measure' => 'L', 'cost_price' => 20, 'minimum_stock' => 1]);
        $deposito = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Depósito', 'code' => 'D1', 'is_active' => true]);
        $caminhao = Asset::unguarded(fn () => Asset::create(['tenant_id' => $this->tenant->id, 'name' => 'Munck', 'tag' => 'V-1', 'status' => 'disponivel', 'is_vehicle' => true, 'placa' => 'ABC1D23']));
        $quando = now()->subHours(3);

        // a troca que baixou o estoque (movimento exit_fleet de 20 L) e outra, antiga, sem movimento
        StockMovement::create(['tenant_id' => $this->tenant->id, 'part_id' => $oleo->id, 'warehouse_id' => $deposito->id, 'movement_type' => 'exit_fleet', 'quantity' => 20, 'balance_before' => 60, 'balance_after' => 40, 'unit_cost' => 20, 'total_cost' => 400, 'reference_document' => 'Óleo troca — ABC1D23', 'created_by' => $this->admin->id, 'created_at' => $quando]);
        FrotaTrocaOleo::unguarded(fn () => FrotaTrocaOleo::create(['tenant_id' => $this->tenant->id, 'ativo_id' => $caminhao->id, 'tipo' => 'troca', 'odometro' => 1000, 'litros' => 20, 'produto' => '15W40', 'realizado_em' => $quando, 'realizado_por' => $this->admin->id, 'peca_id' => $oleo->id, 'almoxarifado_id' => $deposito->id]));
        FrotaTrocaOleo::unguarded(fn () => FrotaTrocaOleo::create(['tenant_id' => $this->tenant->id, 'ativo_id' => $caminhao->id, 'tipo' => 'troca', 'odometro' => 500, 'litros' => 18, 'produto' => '15W40', 'realizado_em' => now()->subDays(60), 'peca_id' => $oleo->id]));

        $eventos = app(RastreioDoItem::class)->eventos(StockPlacement::PART, (string) $oleo->id);

        $this->assertCount(2, $eventos, 'a baixa e a troca da frota viram um evento so'); // nao duplica
        $unido = $eventos->first();
        $this->assertSame('uso', $unido['tipo']);
        $this->assertStringContainsString('ABC1D23', $unido['detalhe']);
        $this->assertSame(40.0, $unido['saldo']); // trouxe o saldo do movimento de estoque
        $this->assertSame($this->admin->name, $unido['quem']);
        $this->assertNull($eventos->last()['saldo']); // a troca antiga, sem movimento, aparece sozinha
    }

    public function test_o_consumo_e_a_entrada_acompanham_a_quantidade_dos_enderecos(): void
    {
        $this->empresa();
        $papel = $this->material('Papel A4', 30, 5);
        $semEndereco = $this->material('Caneta', 10, 1);
        $a = $this->endereco('A', '01');
        $b = $this->endereco('B', '01');
        $this->colocar($a, StockPlacement::MATERIAL, $papel, 20);
        $this->colocar($b, StockPlacement::MATERIAL, $papel, 10);
        $servico = app(MaterialStockService::class);
        $qtd = fn (StockLocation $e) => (float) StockPlacement::where('stock_location_id', $e->id)->where('item_id', (string) $papel->id)->value('quantity');

        // consumo de 25: sai primeiro do endereco com mais (A: 20 -> 0) e o resto do outro (B: 10 -> 5)
        $servico->consume($papel, $this->unidade, 25, null, (string) $this->admin->id);
        $this->assertSame([0.0, 5.0], [$qtd($a), $qtd($b)]);

        // entrada de 12 soma no endereco que ja tem mais unidades (B)
        $servico->receive($papel, $this->unidade, 12, null, (string) $this->admin->id);
        $this->assertSame([0.0, 17.0], [$qtd($a), $qtd($b)]);

        // ajuste para menos (diferenca negativa)
        $servico->adjust($papel, $this->unidade, 10, (string) $this->admin->id);
        $this->assertSame(10.0, $qtd($a) + $qtd($b));

        // item sem endereco: nada a mexer e nada quebra
        $servico->consume($semEndereco, $this->unidade, 3, null, (string) $this->admin->id);
        $this->assertSame(0, StockPlacement::where('item_id', (string) $semEndereco->id)->count());

        // a visao unica mostra o que ainda nao tem endereco (saldo do sistema - soma dos enderecos)
        $linha = app(EstoqueUnificado::class)->todos()->firstWhere('nome', 'Papel A4');
        $this->assertEquals(10.0, $linha['quantidade']);
        $this->assertEquals(0.0, $linha['nao_alocado']);
    }

    public function test_a_contagem_finalizada_so_mexe_no_endereco_contado(): void
    {
        $this->empresa();
        $papel = $this->material('Papel A4', 30, 5);
        MaterialLocationStock::unguarded(fn () => MaterialLocationStock::create(['tenant_id' => $this->tenant->id, 'material_id' => $papel->id, 'internal_unit_id' => $this->unidade->id, 'current_quantity' => 30]));
        $a = $this->endereco('A', '01');
        $b = $this->endereco('B', '01');
        $this->colocar($a, StockPlacement::MATERIAL, $papel, 20);
        $this->colocar($b, StockPlacement::MATERIAL, $papel, 10);

        $servico = app(ContagemFisica::class);
        $contagem = $servico->criar((string) $this->tenant->id, (string) $this->unidade->id, 'Inventário', null, null, (string) $this->admin->id);
        $servico->registrar($contagem->items()->where('stock_location_id', $a->id)->first(), 20, $this->admin, 'celular'); // A bate
        $servico->registrar($contagem->items()->where('stock_location_id', $b->id)->first(), 7, $this->admin, 'celular'); // B falta 3
        $servico->finalizar($contagem->fresh(), (string) $this->admin->id);

        // o estoque foi para 27 e SO' o endereco B mudou (sem o acompanhamento pausado, o ajuste tiraria do A, que e' o maior)
        $this->assertEquals(27, MaterialLocationStock::where('material_id', $papel->id)->value('current_quantity'));
        $this->assertEquals(20, StockPlacement::where('stock_location_id', $a->id)->value('quantity'));
        $this->assertEquals(7, StockPlacement::where('stock_location_id', $b->id)->value('quantity'));
    }
}
