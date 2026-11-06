<?php

namespace Tests\Feature;

use App\Filament\Central\Resources\ContratoResource;
use App\Filament\Pages\MapaDoEstoquePage;
use App\Filament\Pages\RastreioDoItemPage;
use App\Filament\Pages\VisaoGeralEstoque;
use App\Filament\Resources\StockCountResource\Pages\CreateStockCount;
use App\Filament\Resources\StockCountResource\Pages\EditStockCount;
use App\Filament\Resources\StockCountResource\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\StockLocationResource\Pages\ListStockLocations;
use App\Models\AggregateItem;
use App\Models\AggregateItemType;
use App\Models\InternalUnit;
use App\Models\Material;
use App\Models\MaterialStockMovement;
use App\Models\Plan;
use App\Models\Role;
use App\Models\StockCount;
use App\Models\StockGroup;
use App\Models\StockLocation;
use App\Models\StockPlacement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Estoque\ContagemFisica;
use App\Services\Estoque\EstoqueGrupos;
use App\Services\Estoque\EstoqueQr;
use App\Services\Estoque\MapaDoEstoque;
use App\Support\Pacotes;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/** Telas do estoque estruturado: visão geral com filtros, rastreio, QR e etiquetas, endereços e contagem física no painel. */
class EstoqueTelasTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $admin;

    private InternalUnit $unidade;

    private Material $papel;

    private Material $cabo;

    protected function setUp(): void
    {
        parent::setUp();
        $plano = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true, 'features' => ['tabela_materials' => true, 'tabela_stock_structure' => true]]);
        $this->tenant = Tenant::create(['name' => 'T '.uniqid(), 'slug' => 'et-'.uniqid(), 'plan_id' => $plano->id, 'status' => 'active']);
        $this->admin = User::create(['name' => 'Ana Estoquista', 'email' => 'a-'.uniqid().'@oravel.com.br', 'password' => bcrypt('x'), 'tenant_id' => $this->tenant->id, 'is_approved' => true]);
        $this->admin->forceFill(['email_verified_at' => now()])->save();
        $this->admin->assignRole(Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]));
        $this->actingAs($this->admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->unidade = InternalUnit::unguarded(fn () => InternalUnit::create(['tenant_id' => $this->tenant->id, 'name' => 'Matriz', 'code' => 'MTZ', 'type' => 'matriz', 'is_active' => true]));
        $grupos = app(EstoqueGrupos::class)->garantirPadrao((string) $this->tenant->id)->keyBy('name');
        $this->papel = $this->material('Papel A4', 3, 10, $grupos['Administração e escritório']);
        $this->cabo = $this->material('Cabo de aço', 50, 5, $grupos['Equipamentos']);
        $a = StockLocation::create(['tenant_id' => $this->tenant->id, 'internal_unit_id' => $this->unidade->id, 'aisle' => 'A', 'shelf' => '01', 'level' => '2']);
        StockPlacement::create(['tenant_id' => $this->tenant->id, 'stock_location_id' => $a->id, 'item_type' => 'material', 'item_id' => (string) $this->papel->id, 'quantity' => 3]);
    }

    private function material(string $nome, int $saldo, int $minimo, StockGroup $grupo): Material
    {
        return Material::unguarded(fn () => Material::create(['tenant_id' => $this->tenant->id, 'sku' => 'M-'.strtoupper(substr(md5($nome), 0, 5)), 'name' => $nome, 'unit_cost' => 10, 'min_stock' => $minimo, 'max_stock' => 100, 'current_stock' => $saldo, 'stock_group_id' => $grupo->id]));
    }

    public function test_visao_geral_filtra_por_grupo_situacao_e_endereco(): void
    {
        $adm = StockGroup::where('name', 'Administração e escritório')->first();

        Livewire::test(VisaoGeralEstoque::class)
            ->assertSee('Papel A4')->assertSee('Cabo de aço')->assertSee('Valor por grupo')
            ->call('escolherGrupo', (string) $adm->id)->assertSee('Papel A4')->assertDontSee('Cabo de aço')
            ->call('limpar')->set('situacao', 'abaixo')->assertSee('Papel A4')->assertDontSee('Cabo de aço')
            ->call('limpar')->set('endereco', 'sem')->assertSee('Cabo de aço')->assertDontSee('Papel A4')
            ->call('limpar')->set('busca', 'cabo')->assertSee('Cabo de aço')->assertDontSee('Papel A4');
    }

    public function test_rastreio_do_item_mostra_a_linha_do_tempo(): void
    {
        MaterialStockMovement::create(['tenant_id' => $this->tenant->id, 'material_id' => $this->papel->id, 'type' => 'entrada', 'quantity' => 30, 'balance_after' => 30, 'created_by_user_id' => $this->admin->id]);

        Livewire::withQueryParams(['item' => 'material|'.$this->papel->id])->test(RastreioDoItemPage::class)
            ->assertSee('Papel A4')->assertSee('Linha do tempo do item')->assertSee('Entrada no estoque')->assertSee('saldo 30')->assertSee('Ana Estoquista');
    }

    public function test_rastreio_sem_item_escolhido_orienta_o_usuario(): void
    {
        Livewire::test(RastreioDoItemPage::class)->assertSee('Escolha um item acima');
    }

    public function test_qr_interpreta_link_e_codigo_solto_e_o_link_do_qr_abre_o_item(): void
    {
        $qr = app(EstoqueQr::class);
        $link = $qr->link('material', (string) $this->papel->id);

        $this->assertSame(['tipo' => 'material', 'id' => (string) $this->papel->id], $qr->interpretar($link));
        $this->assertSame(['tipo' => 'material', 'id' => (string) $this->papel->id], $qr->interpretar($this->papel->sku));
        $this->assertNull($qr->interpretar('codigo-que-nao-existe'));
        $this->assertStringContainsString('<svg', $qr->svg('material', (string) $this->papel->id));

        $this->get($link)->assertRedirect(RastreioDoItemPage::getUrl(['item' => 'material|'.$this->papel->id]));
        $endereco = StockLocation::first();
        $this->get($qr->link('endereco', (string) $endereco->id))->assertRedirect();
        $this->get('/admin/estoque/qr/foo/'.$this->papel->id)->assertNotFound();
    }

    public function test_etiquetas_saem_com_qr_e_item_agregado_vira_uma_etiqueta_por_unidade(): void
    {
        $tipo = AggregateItemType::create(['tenant_id' => $this->tenant->id, 'name' => 'Cinta de içamento', 'category' => 'acessorio', 'unit_of_measure' => 'un']);
        foreach (['CI-1', 'CI-2'] as $code) {
            AggregateItem::unguarded(fn () => AggregateItem::create(['tenant_id' => $this->tenant->id, 'aggregate_item_type_id' => $tipo->id, 'code' => $code, 'status' => 'disponivel']));
        }

        $itens = $this->get(route('estoque.etiquetas', ['tipo' => 'itens', 'modelo' => 'pequena']))->assertOk();
        $itens->assertSee('Papel A4')->assertSee('Cabo de aço')->assertSee('Código CI-1', false)->assertSee('Código CI-2', false)->assertSee('<svg', false);
        $this->assertSame(4, substr_count($itens->getContent(), 'class="etq"'));

        $this->get(route('estoque.etiquetas', ['tipo' => 'itens', 'grupo' => (string) StockGroup::where('name', 'Equipamentos')->value('id')]))
            ->assertSee('Cabo de aço')->assertDontSee('Papel A4');

        $this->get(route('estoque.etiquetas', ['tipo' => 'enderecos', 'modelo' => 'grande']))->assertOk()->assertSee('C-A · P-01 · N-2')->assertSee('ENDEREÇO');
    }

    public function test_gerar_enderecos_em_lote_nao_duplica(): void
    {
        $dados = ['internal_unit_id' => $this->unidade->id, 'corredores' => 'B, C', 'prateleiras' => 2, 'niveis' => 2];

        Livewire::test(ListStockLocations::class)->callTableAction('gerar', data: $dados)->assertHasNoTableActionErrors();
        $this->assertSame(8, StockLocation::whereIn('aisle', ['B', 'C'])->count());

        Livewire::test(ListStockLocations::class)->callTableAction('gerar', data: $dados);
        $this->assertSame(8, StockLocation::whereIn('aisle', ['B', 'C'])->count());
    }

    public function test_contagem_pelo_painel_cria_conta_confronta_e_finaliza(): void
    {
        Livewire::test(CreateStockCount::class)
            ->fillForm(['name' => 'Inventário teste', 'internal_unit_id' => $this->unidade->id])
            ->call('create')->assertHasNoFormErrors();

        $contagem = StockCount::where('name', 'Inventário teste')->firstOrFail();
        $this->assertSame(1, $contagem->items()->count());
        $item = $contagem->items()->first();

        $tabela = Livewire::test(ItemsRelationManager::class, ['ownerRecord' => $contagem, 'pageClass' => EditStockCount::class]);
        $tabela->assertCanSeeTableRecords([$item])->assertSee('não contado');
        $tabela->callTableAction('contar', $item, ['quantidade' => 2, 'nota' => 'faltou uma resma']);

        $item = $item->fresh();
        $this->assertEquals(2, $item->counted_quantity);
        $this->assertSame('Ana Estoquista', $item->counted_by_name);
        $this->assertSame('web', $item->entries->first()->source);
        $tabela->filterTable('situacao', 'divergentes')->assertCanSeeTableRecords([$item]);

        Livewire::test(EditStockCount::class, ['record' => $contagem->id])->callAction('finalizar');
        $this->assertSame(StockCount::FINALIZADA, $contagem->fresh()->status);
        $this->assertEquals(2, $this->papel->fresh()->current_stock);
    }

    public function test_a_central_oferece_a_chave_do_estoque_estruturado_no_contrato(): void
    {
        $opcoes = ContratoResource::groupedFeatureOptions();
        $grupo = collect($opcoes)->first(fn ($itens) => isset($itens['tabela_stock_structure']));

        $this->assertNotNull($grupo, 'a chave tabela_stock_structure precisa aparecer em algum grupo do contrato');
        $this->assertStringContainsString('Endereços do Almoxarifado', $grupo['tabela_stock_structure']);
        $this->assertContains('tabela_stock_structure', Pacotes::features('locadora'));
    }

    public function test_mapa_do_estoque_tem_uma_coluna_por_corredor_com_estado_e_cor(): void
    {
        $b = StockLocation::create(['tenant_id' => $this->tenant->id, 'internal_unit_id' => $this->unidade->id, 'aisle' => 'B', 'shelf' => '01', 'level' => '1']);
        StockLocation::create(['tenant_id' => $this->tenant->id, 'internal_unit_id' => $this->unidade->id, 'aisle' => 'B', 'shelf' => '01', 'level' => '2']); // vazio
        StockPlacement::create(['tenant_id' => $this->tenant->id, 'stock_location_id' => $b->id, 'item_type' => 'material', 'item_id' => (string) $this->cabo->id, 'quantity' => 50]);

        $mapa = app(MapaDoEstoque::class)->montar((string) $this->unidade->id);

        $this->assertSame(['A', 'B'], array_column($mapa['colunas'], 'corredor'));
        $this->assertSame(['enderecos' => 3, 'ocupados' => 2, 'vazios' => 1, 'com_problema' => 1, 'destacados' => 3], $mapa['resumo']);
        $celulas = collect($mapa['colunas'])->flatMap(fn ($c) => collect($c['prateleiras'])->flatMap(fn ($p) => $p['niveis']))->keyBy('codigo');
        $this->assertSame('abaixo', $celulas['C-A · P-01 · N-2']['estado']); // papel abaixo do minimo
        $this->assertSame('ok', $celulas['C-B · P-01 · N-1']['estado']);
        $this->assertSame('vazio', $celulas['C-B · P-01 · N-2']['estado']);
        $this->assertSame(MapaDoEstoque::CORES['abaixo'], $celulas['C-A · P-01 · N-2']['cor']);

        // modo grupo: cor do grupo do item; busca e grupo apagam os demais enderecos
        $grupoCor = StockGroup::where('name', 'Equipamentos')->value('color');
        $porGrupo = app(MapaDoEstoque::class)->montar((string) $this->unidade->id, ['modo' => 'grupo']);
        $this->assertSame($grupoCor, collect($porGrupo['colunas'])->flatMap(fn ($c) => collect($c['prateleiras'])->flatMap(fn ($p) => $p['niveis']))->keyBy('codigo')['C-B · P-01 · N-1']['cor']);
        $achado = app(MapaDoEstoque::class)->montar((string) $this->unidade->id, ['busca' => 'cabo']);
        $this->assertSame(1, $achado['resumo']['destacados']);
    }

    public function test_mapa_no_modo_contagem_mostra_o_andamento_e_a_tela_abre_o_detalhe(): void
    {
        $servico = app(ContagemFisica::class);
        $contagem = $servico->criar((string) $this->tenant->id, (string) $this->unidade->id, 'Inventário', null, null, (string) $this->admin->id);
        $celulaDe = fn (array $mapa) => collect($mapa['colunas'])->flatMap(fn ($c) => collect($c['prateleiras'])->flatMap(fn ($p) => $p['niveis']))->firstWhere('codigo', 'C-A · P-01 · N-2');

        $this->assertSame(MapaDoEstoque::CORES['pendente'], $celulaDe(app(MapaDoEstoque::class)->montar((string) $this->unidade->id, ['modo' => 'contagem']))['cor']);

        $servico->registrar($contagem->items()->first(), 2, $this->admin, 'celular'); // esperado 3, contou 2
        $this->assertSame(MapaDoEstoque::CORES['divergente'], $celulaDe(app(MapaDoEstoque::class)->montar((string) $this->unidade->id, ['modo' => 'contagem']))['cor']);

        $endereco = StockLocation::where('aisle', 'A')->first();
        Livewire::test(MapaDoEstoquePage::class)
            ->assertSee('Corredor A')->assertSee('Planta do almoxarifado')
            ->call('selecionar', (string) $endereco->id)->assertSee('Papel A4')->assertSee('por Ana Estoquista') // detalhe do endereço traz a ultima contagem
            ->set('modo', 'contagem')->assertSee('Com divergência')
            ->set('busca', 'papel')->assertSee('com o que você procura');
    }
}
