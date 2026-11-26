<?php

namespace Tests\Feature;

use App\Domain\Intelligence\Models\SuprimentosCarga;
use App\Domain\Intelligence\Models\SuprimentosItem;
use App\Domain\Intelligence\Models\SuprimentosMovimento;
use App\Domain\Intelligence\Support\ItemDeMenuDoModulo;
use App\Domain\Intelligence\Support\ModeloExcel;
use App\Domain\Intelligence\Suprimentos\Catalogo;
use App\Domain\Intelligence\Suprimentos\ExemploDeSuprimentos;
use App\Domain\Intelligence\Suprimentos\ImportadorSuprimentos;
use App\Domain\Intelligence\Suprimentos\IndicadoresDeSuprimentos;
use App\Domain\Intelligence\Suprimentos\ModeloSuprimentos;
use App\Domain\Intelligence\Trial\Models\IntelligenceTrial;
use App\Domain\Intelligence\Trial\Services\TrialCadastro;
use App\Domain\Intelligence\Trial\Support\Planos;
use App\Filament\Pages\IntelligenceSuprimentos;
use App\Models\Plan;
use App\Models\Tenant;
use App\Support\ExcelImport\XlsxReader;
use App\Support\Pacotes;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;

class IntelligenceSuprimentosTest extends TestCase
{
    use DatabaseTransactions;

    private function tenant(): Tenant
    {
        $plan = Plan::create(['name' => 'P '.uniqid(), 'price' => 1, 'base_price' => 1, 'level' => 1, 'billing_cycle' => 'monthly', 'is_active' => true,
            'features' => collect(Pacotes::features('intelligence'))->mapWithKeys(fn ($c) => [$c => true])->all()]);

        return Tenant::create(['name' => 'Locadora '.uniqid(), 'slug' => 'l-'.uniqid(), 'plan_id' => $plan->id, 'status' => 'active']);
    }

    /** Planilha mínima no formato do modelo (linhas já na ordem das colunas do catálogo). */
    private function planilha(array $inventario, array $compras = [], array $movs = [], string $ref = '30/09/2026', bool $semInventario = false): string
    {
        $caminho = tempnam(sys_get_temp_dir(), 'sup').'.xlsx';
        $w = new Writer;
        $w->openToFile($caminho);
        $tit = fn (array $campos) => Row::fromValues(array_column($campos, 'title'));
        $w->getCurrentSheet()->setName(Catalogo::ABA_DADOS);
        $w->addRow($tit(Catalogo::dados()));
        $w->addRow(Row::fromValues([$ref, 90]));
        if (! $semInventario) {
            $w->addNewSheetAndMakeItCurrent()->setName(Catalogo::ABA_INVENTARIO);
            $w->addRow($tit(Catalogo::inventario()));
            foreach ($inventario as $l) {
                $w->addRow(Row::fromValues($l));
            }
        }
        if ($compras) {
            $w->addNewSheetAndMakeItCurrent()->setName(Catalogo::ABA_COMPRAS);
            $w->addRow($tit(Catalogo::compras()));
            foreach ($compras as $l) {
                $w->addRow(Row::fromValues($l));
            }
        }
        if ($movs) {
            $w->addNewSheetAndMakeItCurrent()->setName(Catalogo::ABA_MOVIMENTOS);
            $w->addRow($tit(Catalogo::movimentos()));
            foreach ($movs as $l) {
                $w->addRow(Row::fromValues($l));
            }
        }
        $w->close();

        return $caminho;
    }

    private function importar(Tenant $t, string $arquivo, bool $simular = false): array
    {
        try {
            return app(ImportadorSuprimentos::class)->importar($arquivo, $t, null, $simular, 'teste.xlsx');
        } finally {
            @unlink($arquivo);
        }
    }

    public function test_modelo_de_suprimentos_tem_abas_proprias_e_diferentes_do_modelo_geral(): void
    {
        $p = sys_get_temp_dir().'/modelo-sup-'.uniqid().'.xlsx';
        ModeloSuprimentos::escrever($p);
        foreach ([Catalogo::ABA_DADOS, Catalogo::ABA_INVENTARIO, Catalogo::ABA_COMPRAS, Catalogo::ABA_MOVIMENTOS, Catalogo::ABA_INSTRUCOES] as $aba) {
            $this->assertNotNull(XlsxReader::rowsDaAba($p, $aba), "falta a aba {$aba}");
        }
        $dados = implode('|', array_map('strval', XlsxReader::rowsDaAba($p, Catalogo::ABA_DADOS)[1]));
        $this->assertStringNotContainsString('Total de Equipamentos', $dados, 'não é o modelo geral');
        @unlink($p);

        // o modelo GERAL não serve para Suprimentos (e o aviso diz isso)
        $geral = sys_get_temp_dir().'/modelo-geral-'.uniqid().'.xlsx';
        ModeloExcel::escrever($geral);
        $r = $this->importar($this->tenant(), $geral);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Inventário', implode(' ', $r['erros']));
        $p = sys_get_temp_dir().'/modelo-sup-'.uniqid().'.xlsx';
        ModeloSuprimentos::escrever($p);
        $cab = array_map('strval', XlsxReader::rowsDaAba($p, Catalogo::ABA_INVENTARIO)[1]);
        $this->assertContains('Categoria *', $cab, 'a categoria é obrigatória');
        @unlink($p);
    }

    public function test_importa_inventario_compras_e_movimentos_com_indicadores_categorias_e_listas(): void
    {
        $t = $this->tenant();
        $arq = $this->planilha(
            [
                ['PEC-1', 'Filtro de óleo', 'Peças', 'un', 2, 10, 50, '10/09/2026', '20/09/2026', 'A1'],      // abaixo do mínimo
                ['PEC-2', 'Correia', 'Peças', 'un', 30, 5, 100, '01/03/2026', '01/03/2026', 'A1'],             // parado (sem saída desde março)
                ['ESC-1', 'Papel A4', 'Itens de Escritório', 'cx', 20, 5, 10, '05/09/2026', '25/09/2026', 'A2'],
                ['EQP-1', 'Compressor', 'Equipamentos', 'un', 1, 1, 2000, '', '', ''],                        // sem saída (nunca saiu)
            ],
            [['PC-1', '01/09/2026', 'PEC-1', 'Forn A', 10, 500, '10/09/2026', '08/09/2026', 'Não'],
                ['PC-2', '05/09/2026', 'ESC-1', 'Forn B', 5, 50, '12/09/2026', '20/09/2026', 'Sim'],         // 8 dias de atraso, emergencial
                ['PC-3', '10/09/2026', 'PEC-2', 'Forn A', 2, 200, '20/09/2026', '', 'Não']],                 // aberto e atrasado
            [['15/09/2026', 'PEC-1', 'Saída', 8, 'Oficina', 'OS-1'], ['25/09/2026', 'ESC-1', 'Saída', 2, '', 'REQ-2'], ['01/09/2026', 'PEC-1', 'Entrada', 10, '', 'NF-1']],
        );

        $r = $this->importar($t, $arq);

        $this->assertTrue($r['ok'], implode(' | ', $r['erros']));
        $this->assertSame(['itens' => 4, 'compras' => 3, 'movimentos' => 3], $r['totais']);
        $ind = $r['indicadores'];
        $this->assertEqualsWithDelta(2 * 50 + 30 * 100 + 20 * 10 + 1 * 2000, $ind['valor_estoque']['valor'], 0.01);
        $this->assertEqualsWithDelta(25.0, $ind['itens_abaixo_minimo_pct']['valor'], 0.1, '1 de 4 itens com mínimo');
        $this->assertSame(50.0, $ind['entregas_no_prazo_pct']['valor'], '1 de 2 chegadas no prazo');
        $this->assertSame(4.0, $ind['atraso_medio_dias']['valor'], '(0 + 8) / 2');
        $this->assertEqualsWithDelta(33.33, $ind['compras_emergenciais_pct']['valor'], 0.1);
        $this->assertSame(100.0, $ind['pedidos_abertos_atrasados_pct']['valor']);
        $this->assertSame(50.0, $ind['saidas_sem_destino_pct']['valor'], '1 das 2 saídas sem destino');
        $this->assertArrayHasKey('nota', $ind['giro_estoque']);
        $this->assertNotNull($r['diagnostico']['pontuacao']);

        $carga = SuprimentosCarga::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail();
        $this->assertSame(4, SuprimentosItem::withoutGlobalScopes()->where('carga_id', $carga->id)->count());
        $this->assertSame('Itens de Escritório', SuprimentosItem::withoutGlobalScopes()->where('codigo', 'ESC-1')->value('categoria'));
        $l = $carga->listas;
        $this->assertSame(['PEC-1'], array_column($l['abaixo_minimo'], 'codigo'));
        $this->assertEquals(18, $l['abaixo_minimo'][0]['sugestao_compra'], '2x o mínimo (20) menos o que tem (2)');
        $this->assertEqualsWithDelta(18 * 50, $l['custo_reposicao'], 0.01);
        $this->assertContains('PEC-2', array_column($l['parados'], 'codigo'));
        $this->assertSame('PEC-1', $l['maior_giro'][0]['codigo'], 'o item que mais saiu');
        $this->assertSame('Oficina', $l['destinos'][0]['destino']);
        $this->assertSame('Forn A', $l['fornecedores'][0]['fornecedor']);
        $this->assertSame(['Peças', 'Equipamentos', 'Itens de Escritório'], array_column($l['por_categoria'], 'categoria'), 'ordenadas pelo valor em estoque');
        $this->assertSame('ESC-1', $l['emergenciais'][0]['codigo']);
    }

    public function test_so_o_inventario_basta_e_o_sistema_diz_o_que_faltou(): void
    {
        $t = $this->tenant();
        $r = $this->importar($t, $this->planilha([['A-1', 'Item', 'Peças', 'un', 3, 10, 5, '', '', '']]));

        $this->assertTrue($r['ok'], implode(' | ', $r['erros']));
        $this->assertSame(['valor_estoque', 'itens_abaixo_minimo_pct'], array_keys($r['indicadores']));
        $texto = implode(' ', $r['avisos']);
        $this->assertStringContainsString('Compras', $texto);
        $this->assertStringContainsString('Movimentações', $texto);
        $this->assertStringContainsString('parados', $texto);
    }

    public function test_erros_claros_inventario_ausente_codigo_repetido_e_campo_invalido(): void
    {
        $t = $this->tenant();

        $r = $this->importar($t, $this->planilha([], semInventario: true));
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('"Inventário" não encontrada', $r['erros'][0]);

        $r = $this->importar($t, $this->planilha([['X-1', 'A', 'Peças', 'un', 1, 1, 1, '', '', ''], ['x-1', 'B', 'Peças', 'un', 1, 1, 1, '', '', '']]));
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('repetido', implode(' ', $r['erros']));

        $r = $this->importar($t, $this->planilha([['X-2', 'A', 'Peças', 'un', 'abc', 1, 1, '', '', '']]));
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('Quantidade em Estoque', implode(' ', $r['erros']));
        $this->assertSame(0, SuprimentosCarga::withoutGlobalScopes()->where('tenant_id', $t->id)->count(), 'nada gravado com erro');
    }

    public function test_categoria_desconhecida_vira_outros_e_planilha_repetida_e_recusada(): void
    {
        $t = $this->tenant();
        $linha = ['Z-1', 'Coisa', 'Categoria inventada', 'un', 5, 1, 3, '', '', ''];

        $r = $this->importar($t, $this->planilha([$linha]));
        $this->assertTrue($r['ok'], implode(' | ', $r['erros']));
        $this->assertSame('Outros', SuprimentosItem::withoutGlobalScopes()->where('codigo', 'Z-1')->value('categoria'));

        $r2 = $this->importar($t, $this->planilha([$linha]));
        $this->assertFalse($r2['ok']);
        $this->assertStringContainsString('idêntica', $r2['erros'][0]);
    }

    public function test_simular_valida_sem_gravar(): void
    {
        $t = $this->tenant();
        $r = $this->importar($t, $this->planilha([['S-1', 'Item', 'Peças', 'un', 3, 10, 5, '', '', '']]), simular: true);

        $this->assertTrue($r['ok']);
        $this->assertTrue($r['simulacao']);
        $this->assertSame(0, SuprimentosCarga::withoutGlobalScopes()->where('tenant_id', $t->id)->count());
    }

    public function test_sem_nenhuma_informacao_de_saida_nao_declara_item_parado(): void
    {
        $calc = new IndicadoresDeSuprimentos;
        $itens = [['codigo' => 'A', 'descricao' => 'A', 'categoria' => 'Peças', 'unidade' => 'un', 'quantidade' => 5.0, 'estoque_minimo' => 1.0, 'custo_unitario' => 10.0, 'ultima_entrada' => null, 'ultima_saida' => null]];

        $r = $calc->calcular($itens, [], [], Carbon::parse('2026-09-30'), 90);

        $this->assertArrayNotHasKey('itens_parados_pct', $r['indicadores']);
        $this->assertSame([], $r['listas']['parados']);
    }

    public function test_exemplo_gera_carga_marcada_com_categorias_e_rege_as_mesmas_regras_do_geral(): void
    {
        $t = $this->tenant();
        $gerador = app(ExemploDeSuprimentos::class);

        $this->assertSame(1, $gerador->gerar($t));
        $this->assertSame(0, $gerador->gerar($t), 'idempotente');

        $carga = SuprimentosCarga::withoutGlobalScopes()->where('tenant_id', $t->id)->firstOrFail();
        $this->assertSame('demonstracao', $carga->origem);
        $this->assertTrue(ExemploDeSuprimentos::soExemplo($t->id));
        $this->assertSame(68, $carga->total_itens);
        $this->assertSame(8, count($carga->listas['por_categoria']), 'todas as categorias');
        $this->assertGreaterThan(0, $carga->listas['abaixo_minimo_total']);
        $this->assertNotEmpty($carga->listas['maior_giro']);
        $this->assertNotEmpty($carga->listas['parados']);
        $this->assertNotEmpty($carga->listas['pedidos_atrasados']);
        $this->assertGreaterThan(8, count($carga->indicadores), 'o exemplo preenche as três abas: quase todos os indicadores');
        $this->assertGreaterThan(0, SuprimentosMovimento::withoutGlobalScopes()->where('carga_id', $carga->id)->count());

        $this->assertSame(1, $gerador->limpar($t));
        $this->assertFalse(ExemploDeSuprimentos::existe($t->id));
        $this->assertSame(0, SuprimentosItem::withoutGlobalScopes()->where('tenant_id', $t->id)->count(), 'as linhas saem em cascata');
    }

    private function contaDeTeste(): IntelligenceTrial
    {
        return app(TrialCadastro::class)->cadastrar(['nome' => 'Rui', 'empresa' => 'Locadora Rui', 'whatsapp' => '+5519966665555', 'email' => 'rui@locadora-rui.test'])['trial'];
    }

    public function test_modulo_so_abre_no_degrau_standard_e_o_item_de_menu_aparece_desabilitado_no_teste(): void
    {
        $trial = $this->contaDeTeste();
        $this->actingAs($trial->user);

        // Teste grátis e Básico: sem o módulo, mas o item de menu existe, visível e sem link (desabilitado)
        $this->assertFalse(IntelligenceSuprimentos::canAccess());
        $item = ItemDeMenuDoModulo::make('Suprimentos', 'heroicon-o-archive-box', 4, IntelligenceSuprimentos::class, 'menu_intelligence_suprimentos', 'Standard');
        $this->assertTrue($item->isVisible(), 'aparece no menu');
        $this->assertNull($item->getUrl(), 'sem link: desabilitado');
        $this->assertSame('Standard', $item->getBadge());

        // Standard contratado (módulo liberado no config): abre, com link
        config(['intelligence_trial.modulos_prontos' => ['base', 'suprimentos', 'comercial', 'logistica']]);
        $trial->forceFill(['contracted_at' => now(), 'plano_chave' => 'standard'])->save();
        $this->assertTrue(Planos::temModulo($trial->fresh(), 'suprimentos'));
        $this->assertTrue(IntelligenceSuprimentos::canAccess());
        $this->assertSame(IntelligenceSuprimentos::getUrl(), $item->getUrl());
        $this->assertNull($item->getBadge());

        // Básico contratado: volta a ficar desabilitado
        $trial->forceFill(['plano_chave' => 'basico'])->save();
        $this->assertFalse(IntelligenceSuprimentos::canAccess());
    }

    public function test_tela_abre_com_exemplo_na_primeira_vez_filtra_por_categoria_e_limpa(): void
    {
        $trial = $this->contaDeTeste();
        $trial->forceFill(['contracted_at' => now(), 'plano_chave' => 'standard'])->save();
        config(['intelligence_trial.modulos_prontos' => ['base', 'suprimentos', 'comercial', 'logistica']]);
        $this->actingAs($trial->user);

        $c = Livewire::test(IntelligenceSuprimentos::class);
        $c->assertSee('DEMONSTRAÇÃO')->assertSee('Quais itens precisamos repor?')->assertSee('Quais itens têm maior giro')->assertSee('Itens a repor');
        $this->assertTrue(ExemploDeSuprimentos::existe($trial->tenant_id));
        $this->assertNotNull($trial->fresh()->exemplo_suprimentos_oferecido_em);

        $total = count($c->instance()->listas()['abaixo_minimo']);
        $c->set('categoria', 'Equipamentos');
        $filtradas = $c->instance()->listas()['abaixo_minimo'];
        $this->assertSame([], array_diff(array_column($filtradas, 'categoria'), ['Equipamentos']), 'só da categoria escolhida');
        $this->assertLessThanOrEqual($total, count($filtradas));

        $c->call('limparExemplo')->assertSee('Ainda não há planilha de Suprimentos')->assertSee('Ver dados de exemplo');
        $this->assertFalse(ExemploDeSuprimentos::existe($trial->tenant_id));

        $c->call('verExemplo');
        $this->assertTrue(ExemploDeSuprimentos::existe($trial->tenant_id), 'pode voltar a ver o exemplo');
    }

    public function test_exemplo_de_suprimentos_some_de_vez_depois_de_30_dias(): void
    {
        $trial = $this->contaDeTeste();
        $gerador = app(ExemploDeSuprimentos::class);
        $gerador->aoAbrir($trial);
        $this->assertTrue(ExemploDeSuprimentos::existe($trial->tenant_id));

        $this->travelTo(now()->addDays(31));
        $this->artisan('intelligence:exemplo-expirar')->assertSuccessful();

        $this->assertFalse(ExemploDeSuprimentos::existe($trial->tenant_id));
        $this->assertFalse(ExemploDeSuprimentos::podeGerar($trial->fresh()), 'passado o prazo não volta');
    }

    public function test_chaves_do_modulo_estao_no_pacote_intelligence_e_nunca_no_erp(): void
    {
        $intel = Pacotes::features('intelligence');
        $this->assertContains('tabela_intelligence_suprimentos', $intel, 'sem esta chave o acesso aos dados é negado até no plano Standard');
        $this->assertContains('menu_intelligence_suprimentos', $intel);

        $erp = Pacotes::features('locadora');
        $this->assertNotContains('tabela_intelligence_suprimentos', $erp, 'produto do Intelligence não vaza para o ERP');
        $this->assertNotContains('menu_intelligence_suprimentos', $erp);
    }

    public function test_conta_de_teste_nasce_com_as_chaves_de_suprimentos_no_contrato(): void
    {
        $trial = $this->contaDeTeste();

        $this->assertTrue($trial->tenant->fresh()->hasFeature('tabela_intelligence_suprimentos'));
        $this->assertTrue($trial->tenant->fresh()->hasFeature('menu_intelligence_suprimentos'));
    }
}
