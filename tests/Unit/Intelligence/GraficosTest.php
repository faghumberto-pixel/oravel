<?php

namespace Tests\Unit\Intelligence;

use App\Domain\Intelligence\Support\Graficos;
use PHPUnit\Framework\TestCase;

/** Puro (sem app/banco): vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Unit/Intelligence */
class GraficosTest extends TestCase
{
    private function resumo(int $fotos = 2): array
    {
        $hist = [
            ['data' => '2026-09-30', 'geral' => 60.0, 'dados' => ['frota_total' => 100, 'frota_locada' => 70, 'frota_indisponivel' => 20, 'receita_mensal' => 400000, 'custo_manutencao' => 40000, 'custo_operacional' => 200000, 'os_corretivas' => 40, 'os_preventivas' => 10, 'tempo_medio_reparo_h' => 30]],
            ['data' => '2026-10-31', 'geral' => 77.0, 'dados' => ['frota_total' => 100, 'frota_locada' => 80, 'frota_indisponivel' => 10, 'receita_mensal' => 450000, 'custo_manutencao' => 35000, 'custo_operacional' => 200000, 'os_corretivas' => 30, 'os_preventivas' => 25, 'tempo_medio_reparo_h' => 18]],
        ];
        $hist = array_slice($hist, -$fotos);

        return [
            'atual' => ['data' => end($hist)['data'], 'geral' => ['nota' => 77.0, 'faixa' => 'Bom', 'delta' => $fotos > 1 ? 17.0 : null]],
            'anterior' => $fotos > 1 ? '2026-09-30' : null,
            'total_snapshots' => $fotos,
            'areas' => [
                ['chave' => 'frota', 'rotulo' => 'Saúde da Frota', 'nota' => 90.0, 'faixa' => 'Excelente', 'delta' => $fotos > 1 ? 20.0 : null, 'serie' => [70.0, 90.0], 'serie_alinhada' => [70.0, 90.0]],
                ['chave' => 'financeira', 'rotulo' => 'Saúde Financeira', 'nota' => 60.0, 'faixa' => 'Bom', 'delta' => $fotos > 1 ? 5.0 : null, 'serie' => [55.0, 60.0], 'serie_alinhada' => [55.0, 60.0]],
                ['chave' => 'oficina', 'rotulo' => 'Saúde da Oficina', 'nota' => 50.0, 'faixa' => 'Atenção', 'delta' => $fotos > 1 ? -4.0 : null, 'serie' => [54.0, 50.0], 'serie_alinhada' => [54.0, 50.0]],
                ['chave' => 'geral', 'rotulo' => 'Índice Geral', 'nota' => 77.0, 'faixa' => 'Bom', 'delta' => 17.0, 'serie' => [60.0, 77.0], 'serie_alinhada' => [60.0, 77.0]],
            ],
            'indicadores' => [
                ['nota' => 100.0, 'area' => 'frota', 'chave' => 'disponibilidade_frota', 'titulo' => 'Disponibilidade da Frota', 'unidade' => '%', 'valor' => 90.0, 'anterior' => 80.0, 'delta' => 10.0, 'sentido' => 'melhor', 'serie' => [80.0, 90.0]],
                ['nota' => 20.0, 'area' => 'financeira', 'chave' => 'inadimplencia_pct', 'titulo' => 'Inadimplência', 'unidade' => '%', 'valor' => 12.0, 'anterior' => 6.0, 'delta' => 6.0, 'sentido' => 'pior', 'serie' => [6.0, 12.0]],
                ['nota' => null, 'area' => 'frota', 'chave' => 'receita_por_equipamento', 'titulo' => 'Receita por Equipamento', 'unidade' => 'R$', 'valor' => 4500.0, 'anterior' => 4000.0, 'delta' => 500.0, 'sentido' => null, 'serie' => [4000.0, 4500.0]],
            ],
            'destaques' => [],
            'historico' => $hist,
        ];
    }

    public function test_gera_todos_os_graficos_com_dois_snapshots(): void
    {
        $g = (new Graficos)->todos($this->resumo());

        foreach (['geral', 'radar', 'evolucao', 'frota', 'financeiro', 'oficina', 'notas', 'variacao', 'polar', 'prioridade', 'custos', 'frota_tempo'] as $k) {
            $this->assertNotNull($g[$k], $k);
            $this->assertArrayHasKey('type', $g[$k]);
            $this->assertNotEmpty($g[$k]['data']['datasets'], $k);
            $this->assertNotNull(json_encode($g[$k]), "$k deve virar JSON");
        }
        // tipos diversificados: rosca/medidor, radar, linha, barras (mista e horizontal)
        $this->assertSame(['doughnut', 'radar', 'line', 'doughnut', 'bar', 'bar', 'bar', 'bar', 'polarArea', 'bubble', 'pie', 'line'], array_values(array_map(fn ($c) => $c['type'], $g)));
        $this->assertSame(180, $g['geral']['options']['circumference']);
        $this->assertSame([77.0, 23.0], $g['geral']['data']['datasets'][0]['data']);
    }

    public function test_dados_dos_graficos_batem_com_os_numeros(): void
    {
        $g = (new Graficos)->todos($this->resumo());

        $this->assertSame([80, 10, 10], $g['frota']['data']['datasets'][0]['data'], 'locados, disponíveis sem locar, indisponíveis');
        $this->assertSame(['30/09/26', '31/10/26'], $g['evolucao']['data']['labels']);
        $resultado = $g['financeiro']['data']['datasets'][0];
        $this->assertSame('Resultado', $resultado['label']);
        $this->assertEquals([160000, 215000], $resultado['data']);
        $of = $g['oficina']['data']['datasets'];
        $this->assertSame([40, 30], $of[0]['data']);
        $this->assertSame('y1', $of[2]['yAxisID']);
        $this->assertSame(3, count($g['radar']['data']['labels']), 'radar sem o índice geral');
        $this->assertSame('Fotografia anterior', $g['radar']['data']['datasets'][0]['label']);
    }

    public function test_variacao_e_divergente_e_notas_ordenadas(): void
    {
        $g = (new Graficos)->todos($this->resumo());

        $this->assertSame(['Disponibilidade da Frota', 'Inadimplência'], $g['variacao']['data']['labels']);
        $this->assertSame([12.5, -100.0], $g['variacao']['data']['datasets'][0]['data'], 'melhora positiva, piora negativa');
        $this->assertSame(['#16a34a', '#dc2626'], $g['variacao']['data']['datasets'][0]['backgroundColor']);
        $this->assertSame([20.0, 100.0], $g['notas']['data']['datasets'][0]['data'], 'pior nota primeiro; informativo (sem nota) fica de fora');
        $this->assertSame('y', $g['notas']['options']['indexAxis']);
    }

    public function test_com_uma_so_fotografia_omite_o_que_depende_de_evolucao(): void
    {
        $g = (new Graficos)->todos($this->resumo(1));

        $this->assertNull($g['evolucao']);
        $this->assertCount(1, $g['radar']['data']['datasets'], 'sem fotografia anterior, o radar mostra só o atual');
        $this->assertNotNull($g['geral']);
        $this->assertNotNull($g['frota']);
        $this->assertNotNull($g['notas']);
    }

    public function test_sem_dado_suficiente_o_grafico_some_em_vez_de_quebrar(): void
    {
        $r = $this->resumo();
        $r['historico'] = array_map(fn ($h) => ['data' => $h['data'], 'geral' => $h['geral'], 'dados' => []], $r['historico']);
        $r['atual']['geral'] = null;
        $r['areas'] = [];
        $r['indicadores'] = [];
        $g = (new Graficos)->todos($r);

        foreach (['geral', 'radar', 'frota', 'financeiro', 'oficina', 'notas', 'variacao', 'polar', 'prioridade', 'custos'] as $k) {
            $this->assertNull($g[$k], $k);
        }
    }

    public function test_graficos_novos_polar_bolhas_pizza_e_frota_no_tempo(): void
    {
        $g = new Graficos;
        $r = $this->resumo();
        // frota_tempo precisa de séries do tamanho do histórico
        $r['indicadores'][] = ['nota' => 80.0, 'area' => 'frota', 'chave' => 'utilizacao_frota', 'titulo' => 'Utilização', 'unidade' => '%', 'valor' => 80.0, 'anterior' => 70.0, 'delta' => 10.0, 'sentido' => 'melhor', 'serie' => [70.0, 80.0]];

        $polar = $g->areasPolar($r);
        $this->assertSame(3, count($polar['data']['labels']));
        $this->assertStringStartsWith('rgba(', $polar['data']['datasets'][0]['backgroundColor'][0]);

        $b = $g->matrizPrioridade($r);
        $this->assertSame('bubble', $b['type']);
        $this->assertGreaterThanOrEqual(2, count($b['data']['datasets']));
        $inad = collect($b['data']['datasets'])->firstWhere('label', 'Inadimplência');
        $this->assertSame(-60.0, (float) $inad['data'][0]['y'], 'piora forte é limitada ao eixo (-60)');
        $this->assertSame(20.0, (float) $inad['data'][0]['x']);

        $this->assertSame([40000.0, 200000.0], array_map('floatval', (function () use ($g, $r) {
            $r['historico'] = array_slice($r['historico'], -1);

            return $g->composicaoCustos(['historico' => [['dados' => ['custo_manutencao' => 40000, 'custo_operacional' => 200000]]]] + $r)['data']['datasets'][0]['data'];
        })()));

        $ft = $g->frotaNoTempo($r);
        $this->assertSame('Utilização (%)', $ft['data']['datasets'][1]['label'] ?? $ft['data']['datasets'][0]['label']);
        $this->assertTrue($ft['data']['datasets'][0]['fill']);
    }

    public function test_kpis_tem_variacao_e_sentido(): void
    {
        $k = collect((new Graficos)->kpis($this->resumo()));

        $receita = $k->firstWhere('titulo', 'Receita do mês');
        $this->assertSame('R$ 450.000', $receita['valor']);
        $this->assertSame('▲ R$ 50.000', $receita['delta']);
        $this->assertSame('melhor', $receita['sentido']);
        $this->assertSame('melhor', $k->firstWhere('titulo', 'Resultado do mês')['sentido']);
        $this->assertSame('melhor', $k->firstWhere('titulo', 'OS corretivas')['sentido'], 'menos corretivas é melhor');
        foreach ($k as $tile) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $tile['cor']);
        }
    }

    public function test_mapa_de_calor_e_comparacao_interna(): void
    {
        $g = new Graficos;
        $calor = $g->calor($this->resumo());
        $this->assertSame(['30/09/26', '31/10/26'], $calor['colunas']);
        $this->assertSame([70.0, 90.0], $calor['linhas'][0]['notas']);
        $this->assertNull($g->calor($this->resumo(1)));

        $c = ['de' => '2026-09-30', 'para' => '2026-10-31', 'areas' => [
            ['chave' => 'frota', 'rotulo' => 'Saúde da Frota', 'de' => 70.0, 'para' => 90.0, 'delta' => 20.0],
            ['chave' => 'financeira', 'rotulo' => 'Saúde Financeira', 'de' => 55.0, 'para' => 60.0, 'delta' => 5.0],
            ['chave' => 'oficina', 'rotulo' => 'Saúde da Oficina', 'de' => 54.0, 'para' => 50.0, 'delta' => -4.0],
            ['chave' => 'geral', 'rotulo' => 'Índice Geral', 'de' => 60.0, 'para' => 77.0, 'delta' => 17.0],
        ]];
        $radar = $g->comparacaoRadar($c);
        $this->assertSame(['30/09/2026', '31/10/2026'], array_column($radar['data']['datasets'], 'label'));
        $this->assertCount(3, $radar['data']['labels'], 'radar sem o índice geral');
        $barras = $g->comparacaoBarras($c);
        $this->assertCount(4, $barras['data']['labels'], 'barras incluem o índice geral');
        $this->assertSame([77.0], array_slice($barras['data']['datasets'][1]['data'], -1));
    }

    public function test_graficos_da_analise_por_equipamento(): void
    {
        $g = new Graficos;

        $pareto = $g->paretoEquipamentos(['itens' => [
            ['patrimonio' => 'A', 'nome' => 'a', 'custo' => 600.0, 'acumulado_pct' => 60.0, 'classificacao' => 'trocar_vender'],
            ['patrimonio' => 'B', 'nome' => 'b', 'custo' => 300.0, 'acumulado_pct' => 90.0, 'classificacao' => 'saudavel'],
        ], 'total' => 1000.0, 'topo_pct' => 90.0]);
        $this->assertSame('bar', $pareto['type']);
        $this->assertSame(['A', 'B'], $pareto['data']['labels']);
        $this->assertSame([60.0, 90.0], $pareto['data']['datasets'][0]['data'], 'a curva é o % acumulado');
        $this->assertSame(['#dc2626', '#16a34a'], $pareto['data']['datasets'][1]['backgroundColor'], 'barra na cor da classificação');
        $this->assertSame('y1', $pareto['data']['datasets'][0]['yAxisID']);
        $this->assertNull($g->paretoEquipamentos(['itens' => [], 'total' => 0.0, 'topo_pct' => 0.0]));

        $pts = [
            ['patrimonio' => 'A', 'nome' => 'a', 'valor' => 100000.0, 'receita' => 500.0, 'custo' => 4000.0, 'classificacao' => 'trocar_vender'],
            ['patrimonio' => 'B', 'nome' => 'b', 'valor' => 50000.0, 'receita' => 5000.0, 'custo' => 100.0, 'classificacao' => 'saudavel'],
            ['patrimonio' => 'C', 'nome' => 'c', 'valor' => 60000.0, 'receita' => 4000.0, 'custo' => 0.0, 'classificacao' => 'saudavel'],
        ];
        $b = $g->valorReceita($pts);
        $this->assertSame('bubble', $b['type']);
        $this->assertSame(['Considerar trocar ou vender', 'Saudável'], array_column($b['data']['datasets'], 'label'), 'uma série por classificação, só as que existem');
        $maior = $b['data']['datasets'][0]['data'][0];
        $this->assertSame(20.0, (float) $maior['r'], 'o de maior custo tem a maior bolha');
        $this->assertSame(4.0, (float) $b['data']['datasets'][1]['data'][1]['r'], 'custo zero = bolha mínima');
        $this->assertNull($g->valorReceita([$pts[0]]), 'um ponto só não vira gráfico');

        $h = fn (string $d, float $u, float $rc, float $c) => ['data' => $d, 'utilizacao_pct' => $u, 'disponibilidade_pct' => 90.0, 'receita' => $rc, 'custo_mes' => $c, 'os_corretivas' => 2, 'os_preventivas' => 1, 'reincidencias' => 1];
        $hist = $g->equipamentoHistorico([$h('2026-08-31', 50.0, 4000.0, 900.0), $h('2026-09-30', 60.0, 5000.0, 700.0)]);
        $this->assertSame(['31/08/26', '30/09/26'], $hist['uso']['data']['labels']);
        $this->assertSame([50.0, 60.0], $hist['uso']['data']['datasets'][0]['data']);
        $this->assertSame([900.0, 700.0], $hist['dinheiro']['data']['datasets'][1]['data']);
        $this->assertSame('line', $hist['manutencao']['data']['datasets'][2]['type']);
        $this->assertSame(['uso' => null, 'dinheiro' => null, 'manutencao' => null], $g->equipamentoHistorico([$h('2026-08-31', 50.0, 1.0, 1.0)]), 'uma fotografia: sem evolução');
        $this->assertSame(['trocar_vender', 'revisar', 'risco_parada', 'subutilizado', 'saudavel', 'sem_dados'], array_keys(Graficos::CLASSE_COR));
    }
}
