<?php

namespace App\Domain\Intelligence\Support;

use Carbon\Carbon;

/**
 * Monta as configurações de gráfico (Chart.js, o mesmo do Filament) do Dashboard Executivo a partir do resumo do EvolucaoService.
 * Puro (sem banco): cada método devolve ['type', 'data', 'options'] ou null quando não há dado suficiente para aquele gráfico.
 */
class Graficos
{
    public const COR = ['primaria' => '#4f46e5', 'verde' => '#16a34a', 'ambar' => '#d97706', 'vermelho' => '#dc2626', 'azul' => '#0ea5e9', 'violeta' => '#8b5cf6', 'cinza' => '#94a3b8'];

    public const FAIXA = ['Excelente' => '#16a34a', 'Bom' => '#2563eb', 'Atenção' => '#d97706', 'Crítico' => '#dc2626'];

    private const COR_AREA = ['frota' => '#0ea5e9', 'financeira' => '#16a34a', 'comercial' => '#8b5cf6', 'oficina' => '#d97706', 'operacional' => '#ec4899', 'geral' => '#4f46e5'];

    /** @return array<string, ?array{type: string, data: array, options: array}> */
    public function todos(array $r): array
    {
        return [
            'geral' => $this->indiceGeral($r),
            'radar' => $this->radar($r),
            'evolucao' => $this->evolucao($r),
            'frota' => $this->frota($r),
            'financeiro' => $this->financeiro($r),
            'oficina' => $this->oficina($r),
            'notas' => $this->notasPorIndicador($r),
            'variacao' => $this->variacao($r),
            'polar' => $this->areasPolar($r),
            'prioridade' => $this->matrizPrioridade($r),
            'custos' => $this->composicaoCustos($r),
            'frota_tempo' => $this->frotaNoTempo($r),
        ];
    }

    public function indiceGeral(array $r): ?array
    {
        $g = $r['atual']['geral'] ?? null;
        if (! $g) {
            return null;
        }
        $cor = self::FAIXA[$g['faixa']] ?? self::COR['primaria'];

        return [
            'type' => 'doughnut',
            'data' => ['labels' => ['Índice geral', 'Distância até 100'], 'datasets' => [[
                'data' => [round($g['nota'], 1), round(100 - $g['nota'], 1)],
                'backgroundColor' => [$cor, 'rgba(148,163,184,.22)'], 'borderWidth' => 0, 'borderRadius' => 6,
            ]]],
            'options' => ['rotation' => -90, 'circumference' => 180, 'cutout' => '76%', 'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['display' => false], 'tooltip' => ['enabled' => false]],
                'scales' => ['x' => ['display' => false], 'y' => ['display' => false]]],
        ];
    }

    public function radar(array $r): ?array
    {
        $areas = array_values(array_filter($r['areas'], fn ($a) => $a['chave'] !== 'geral'));
        if (count($areas) < 3) {
            return null;
        }
        $datasets = [[
            'label' => 'Atual', 'data' => array_map(fn ($a) => round($a['nota'], 1), $areas),
            'borderColor' => self::COR['primaria'], 'backgroundColor' => 'rgba(79,70,229,.22)', 'pointBackgroundColor' => self::COR['primaria'], 'borderWidth' => 2,
        ]];
        if ($r['anterior'] && collect($areas)->every(fn ($a) => $a['delta'] !== null)) {
            array_unshift($datasets, [
                'label' => 'Fotografia anterior', 'data' => array_map(fn ($a) => round($a['nota'] - $a['delta'], 1), $areas),
                'borderColor' => self::COR['cinza'], 'backgroundColor' => 'rgba(148,163,184,.12)', 'borderDash' => [5, 4], 'pointRadius' => 2, 'borderWidth' => 2,
            ]);
        }

        return [
            'type' => 'radar',
            'data' => ['labels' => array_map(fn ($a) => $this->curto($a['rotulo']), $areas), 'datasets' => $datasets],
            'options' => ['maintainAspectRatio' => false,
                'scales' => ['r' => ['min' => 0, 'max' => 100, 'ticks' => ['stepSize' => 25, 'backdropColor' => 'transparent']]],
                'plugins' => ['legend' => ['position' => 'bottom']]],
        ];
    }

    /** Linha com área sombreada: índice geral e cada área ao longo das fotografias. Precisa de 2+ fotografias. */
    public function evolucao(array $r): ?array
    {
        if (count($r['historico']) < 2) {
            return null;
        }
        $datasets = [];
        foreach ($r['areas'] as $a) {
            $geral = $a['chave'] === 'geral';
            $datasets[] = [
                'label' => $a['rotulo'], 'data' => $a['serie_alinhada'], 'spanGaps' => true, 'tension' => .35,
                'borderColor' => self::COR_AREA[$a['chave']] ?? self::COR['cinza'], 'backgroundColor' => $geral ? 'rgba(79,70,229,.14)' : 'transparent',
                'fill' => $geral, 'borderWidth' => $geral ? 3.5 : 1.8, 'pointRadius' => $geral ? 4 : 2.5, 'pointHoverRadius' => 6, 'order' => $geral ? 0 : 1,
                'hidden' => ! $geral && count($r['areas']) > 4 && ! in_array($a['chave'], ['frota', 'financeira'], true),
            ];
        }

        return [
            'type' => 'line',
            'data' => ['labels' => $this->rotulosDatas($r), 'datasets' => $datasets],
            'options' => ['maintainAspectRatio' => false, 'interaction' => ['mode' => 'index', 'intersect' => false],
                'scales' => ['y' => ['min' => 0, 'max' => 100]], 'plugins' => ['legend' => ['position' => 'bottom']]],
        ];
    }

    /** Rosca da frota na última fotografia: locada, disponível sem locar, indisponível. */
    public function frota(array $r): ?array
    {
        $d = end($r['historico'])['dados'] ?? [];
        if (! isset($d['frota_total'], $d['frota_locada'], $d['frota_indisponivel']) || $d['frota_total'] <= 0) {
            return null;
        }
        $ociosa = max(0, $d['frota_total'] - $d['frota_locada'] - $d['frota_indisponivel']);

        return [
            'type' => 'doughnut',
            'data' => ['labels' => ['Locados', 'Disponíveis sem locar', 'Indisponíveis'], 'datasets' => [[
                'data' => [(int) $d['frota_locada'], (int) $ociosa, (int) $d['frota_indisponivel']],
                'backgroundColor' => [self::COR['verde'], self::COR['azul'], self::COR['vermelho']], 'borderWidth' => 0, 'hoverOffset' => 8,
            ]]],
            'options' => ['maintainAspectRatio' => false, 'cutout' => '62%', 'plugins' => ['legend' => ['position' => 'bottom']]],
        ];
    }

    /** Barras (receita × custos) com a linha do resultado: o que sobra em R$ a cada fotografia. */
    public function financeiro(array $r): ?array
    {
        $pontos = array_map(fn ($h) => $h['dados'], $r['historico']);
        if (! collect($pontos)->contains(fn ($d) => isset($d['receita_mensal']))) {
            return null;
        }
        $custos = fn ($d) => ($d['custo_manutencao'] ?? 0) + ($d['custo_operacional'] ?? 0);

        return [
            'type' => 'bar',
            'data' => ['labels' => $this->rotulosDatas($r), 'datasets' => [
                ['type' => 'line', 'label' => 'Resultado', 'data' => array_map(fn ($d) => isset($d['receita_mensal']) ? round($d['receita_mensal'] - $custos($d), 2) : null, $pontos),
                    'borderColor' => self::COR['verde'], 'backgroundColor' => self::COR['verde'], 'tension' => .35, 'borderWidth' => 3, 'pointRadius' => 4, 'order' => 0],
                ['label' => 'Receita', 'data' => array_map(fn ($d) => $d['receita_mensal'] ?? null, $pontos), 'backgroundColor' => self::COR['primaria'], 'borderRadius' => 6, 'order' => 1],
                ['label' => 'Manutenção', 'data' => array_map(fn ($d) => $d['custo_manutencao'] ?? null, $pontos), 'backgroundColor' => self::COR['ambar'], 'borderRadius' => 6, 'order' => 1],
                ['label' => 'Demais custos', 'data' => array_map(fn ($d) => $d['custo_operacional'] ?? null, $pontos), 'backgroundColor' => self::COR['cinza'], 'borderRadius' => 6, 'order' => 1],
            ]],
            'options' => ['maintainAspectRatio' => false, 'interaction' => ['mode' => 'index', 'intersect' => false], 'plugins' => ['legend' => ['position' => 'bottom']]],
        ];
    }

    /** Barras empilhadas corretivas × preventivas, com o tempo médio de reparo (h) no eixo da direita. */
    public function oficina(array $r): ?array
    {
        $pontos = array_map(fn ($h) => $h['dados'], $r['historico']);
        if (! collect($pontos)->contains(fn ($d) => isset($d['os_corretivas']) || isset($d['os_preventivas']))) {
            return null;
        }
        $datasets = [
            ['type' => 'bar', 'label' => 'Corretivas', 'data' => array_map(fn ($d) => $d['os_corretivas'] ?? null, $pontos), 'backgroundColor' => self::COR['vermelho'], 'stack' => 'os', 'borderRadius' => 4, 'yAxisID' => 'y'],
            ['type' => 'bar', 'label' => 'Preventivas', 'data' => array_map(fn ($d) => $d['os_preventivas'] ?? null, $pontos), 'backgroundColor' => self::COR['verde'], 'stack' => 'os', 'borderRadius' => 4, 'yAxisID' => 'y'],
        ];
        $scales = ['x' => ['stacked' => true], 'y' => ['stacked' => true, 'title' => ['display' => true, 'text' => 'OS no mês']]];
        if (collect($pontos)->contains(fn ($d) => isset($d['tempo_medio_reparo_h']))) {
            $datasets[] = ['type' => 'line', 'label' => 'Tempo médio de reparo (h)', 'data' => array_map(fn ($d) => $d['tempo_medio_reparo_h'] ?? null, $pontos),
                'borderColor' => self::COR['violeta'], 'backgroundColor' => self::COR['violeta'], 'tension' => .35, 'borderWidth' => 3, 'pointRadius' => 4, 'yAxisID' => 'y1', 'order' => 0];
            $scales['y1'] = ['position' => 'right', 'grid' => ['drawOnChartArea' => false], 'title' => ['display' => true, 'text' => 'Horas']];
        }

        return [
            'type' => 'bar',
            'data' => ['labels' => $this->rotulosDatas($r), 'datasets' => $datasets],
            'options' => ['maintainAspectRatio' => false, 'interaction' => ['mode' => 'index', 'intersect' => false], 'scales' => $scales, 'plugins' => ['legend' => ['position' => 'bottom']]],
        ];
    }

    /** Barras horizontais: a nota (0–100) de cada indicador pontuável, na cor da faixa. */
    public function notasPorIndicador(array $r): ?array
    {
        $itens = array_values(array_filter($r['indicadores'], fn ($i) => $i['nota'] !== null));
        if ($itens === []) {
            return null;
        }
        usort($itens, fn ($a, $b) => $a['nota'] <=> $b['nota']);

        return [
            'type' => 'bar',
            'data' => ['labels' => array_map(fn ($i) => $i['titulo'], $itens), 'datasets' => [[
                'label' => 'Nota', 'data' => array_map(fn ($i) => $i['nota'], $itens), 'borderRadius' => 6,
                'backgroundColor' => array_map(fn ($i) => $this->corDaNota($i['nota']), $itens),
            ]]],
            'options' => ['indexAxis' => 'y', 'maintainAspectRatio' => false, 'scales' => ['x' => ['min' => 0, 'max' => 100]], 'plugins' => ['legend' => ['display' => false]]],
        ];
    }

    /** Barras divergentes: quanto cada indicador melhorou (verde, para a direita) ou piorou (vermelho, para a esquerda), em %. */
    public function variacao(array $r): ?array
    {
        $itens = [];
        foreach ($r['indicadores'] as $i) {
            if (! in_array($i['sentido'], ['melhor', 'pior'], true) || ! $i['anterior']) {
                continue;
            }
            $pct = round(abs($i['delta'] / $i['anterior']) * 100, 1);
            $itens[] = ['titulo' => $i['titulo'], 'pct' => $i['sentido'] === 'melhor' ? $pct : -$pct];
        }
        if ($itens === []) {
            return null;
        }
        usort($itens, fn ($a, $b) => $b['pct'] <=> $a['pct']);

        return [
            'type' => 'bar',
            'data' => ['labels' => array_column($itens, 'titulo'), 'datasets' => [[
                'label' => 'Variação (%)', 'data' => array_column($itens, 'pct'), 'borderRadius' => 6,
                'backgroundColor' => array_map(fn ($i) => $i['pct'] >= 0 ? self::COR['verde'] : self::COR['vermelho'], $itens),
            ]]],
            'options' => ['indexAxis' => 'y', 'maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false]]],
        ];
    }

    /** Área polar: a nota de cada área como "fatia" colorida (leitura rápida de qual área puxa o índice). */
    public function areasPolar(array $r): ?array
    {
        $areas = array_values(array_filter($r['areas'], fn ($a) => $a['chave'] !== 'geral'));
        if (count($areas) < 2) {
            return null;
        }

        return [
            'type' => 'polarArea',
            'data' => ['labels' => array_map(fn ($a) => $this->curto($a['rotulo']), $areas), 'datasets' => [[
                'data' => array_map(fn ($a) => round($a['nota'], 1), $areas),
                'backgroundColor' => array_map(fn ($a) => $this->transparente(self::COR_AREA[$a['chave']] ?? self::COR['cinza'], .72), $areas),
                'borderColor' => array_map(fn ($a) => self::COR_AREA[$a['chave']] ?? self::COR['cinza'], $areas), 'borderWidth' => 2,
            ]]],
            'options' => ['maintainAspectRatio' => false, 'scales' => ['r' => ['min' => 0, 'max' => 100, 'ticks' => ['stepSize' => 25, 'backdropColor' => 'transparent']]],
                'plugins' => ['legend' => ['position' => 'bottom']]],
        ];
    }

    /** Bolhas: cada indicador por nota (→) e variação (↑), do tamanho da distância até a meta. Canto inferior esquerdo = urgente. */
    public function matrizPrioridade(array $r): ?array
    {
        $pontos = [];
        foreach ($r['indicadores'] as $i) {
            if ($i['nota'] === null || ! $i['anterior']) {
                continue;
            }
            $pct = round(abs($i['delta'] / $i['anterior']) * 100, 1);
            $y = $i['sentido'] === 'pior' ? -$pct : ($i['sentido'] === 'melhor' ? $pct : 0.0);
            $pontos[] = ['x' => $i['nota'], 'y' => max(-60, min(60, $y)), 'r' => 6 + (100 - $i['nota']) / 9, 'rotulo' => $i['titulo'], 'cor' => $this->corDaNota($i['nota'])];
        }
        if (count($pontos) < 2) {
            return null;
        }

        return [
            'type' => 'bubble',
            'data' => ['datasets' => array_map(fn ($p) => [
                'label' => $p['rotulo'], 'data' => [['x' => $p['x'], 'y' => $p['y'], 'r' => $p['r']]],
                'backgroundColor' => $this->transparente($p['cor'], .65), 'borderColor' => $p['cor'], 'borderWidth' => 2,
            ], $pontos)],
            'options' => ['maintainAspectRatio' => false, 'plugins' => ['legend' => ['display' => false]],
                'scales' => ['x' => ['min' => 0, 'max' => 105, 'title' => ['display' => true, 'text' => 'Nota do indicador (0 a 100)']],
                    'y' => ['title' => ['display' => true, 'text' => 'Variação desde a fotografia anterior (%)']]]],
        ];
    }

    /** Pizza: para onde vai o custo do mês (manutenção × demais custos) na última fotografia. */
    public function composicaoCustos(array $r): ?array
    {
        $d = end($r['historico'])['dados'] ?? [];
        $m = (float) ($d['custo_manutencao'] ?? 0);
        $o = (float) ($d['custo_operacional'] ?? 0);
        if ($m + $o <= 0) {
            return null;
        }

        return [
            'type' => 'pie',
            'data' => ['labels' => ['Manutenção', 'Demais custos'], 'datasets' => [['data' => [$m, $o], 'backgroundColor' => [self::COR['ambar'], self::COR['violeta']], 'borderWidth' => 0, 'hoverOffset' => 10]]],
            'options' => ['maintainAspectRatio' => false, 'plugins' => ['legend' => ['position' => 'bottom']]],
        ];
    }

    /** Disponibilidade e utilização da frota ao longo do tempo (linhas suaves com área). */
    public function frotaNoTempo(array $r): ?array
    {
        $n = count($r['historico']);
        $series = [];
        foreach (['disponibilidade_frota' => ['Disponibilidade', self::COR['azul']], 'utilizacao_frota' => ['Utilização', self::COR['verde']]] as $chave => [$rotulo, $cor]) {
            $i = collect($r['indicadores'])->firstWhere('chave', $chave);
            if ($i && count($i['serie']) === $n) {
                $series[] = ['label' => $rotulo.' (%)', 'data' => $i['serie'], 'borderColor' => $cor, 'backgroundColor' => $this->transparente($cor, .16), 'fill' => true,
                    'tension' => .4, 'borderWidth' => 3, 'pointRadius' => 4, 'pointHoverRadius' => 7];
            }
        }
        if ($n < 2 || $series === []) {
            return null;
        }

        return ['type' => 'line', 'data' => ['labels' => $this->rotulosDatas($r), 'datasets' => $series],
            'options' => ['maintainAspectRatio' => false, 'interaction' => ['mode' => 'index', 'intersect' => false], 'scales' => ['y' => ['min' => 0, 'max' => 100]], 'plugins' => ['legend' => ['position' => 'bottom']]]];
    }

    /** Comparação interna: radar das áreas nas duas fotografias escolhidas. */
    public function comparacaoRadar(array $c): ?array
    {
        $areas = array_values(array_filter($c['areas'], fn ($a) => $a['chave'] !== 'geral' && $a['de'] !== null && $a['para'] !== null));
        if (count($areas) < 3) {
            return null;
        }

        return [
            'type' => 'radar',
            'data' => ['labels' => array_map(fn ($a) => $this->curto($a['rotulo']), $areas), 'datasets' => [
                ['label' => Carbon::parse($c['de'])->format('d/m/Y'), 'data' => array_map(fn ($a) => round($a['de'], 1), $areas), 'borderColor' => self::COR['cinza'], 'backgroundColor' => 'rgba(148,163,184,.18)', 'borderDash' => [5, 4], 'borderWidth' => 2],
                ['label' => Carbon::parse($c['para'])->format('d/m/Y'), 'data' => array_map(fn ($a) => round($a['para'], 1), $areas), 'borderColor' => self::COR['primaria'], 'backgroundColor' => 'rgba(79,70,229,.25)', 'pointBackgroundColor' => self::COR['primaria'], 'borderWidth' => 2],
            ]],
            'options' => ['maintainAspectRatio' => false, 'scales' => ['r' => ['min' => 0, 'max' => 100, 'ticks' => ['stepSize' => 25, 'backdropColor' => 'transparent']]], 'plugins' => ['legend' => ['position' => 'bottom']]],
        ];
    }

    /** Comparação interna: barras lado a lado (de × para) por área, incluindo o índice geral. */
    public function comparacaoBarras(array $c): ?array
    {
        $areas = array_values(array_filter($c['areas'], fn ($a) => $a['de'] !== null && $a['para'] !== null));
        if ($areas === []) {
            return null;
        }

        return [
            'type' => 'bar',
            'data' => ['labels' => array_map(fn ($a) => $this->curto($a['rotulo']), $areas), 'datasets' => [
                ['label' => Carbon::parse($c['de'])->format('d/m/Y'), 'data' => array_map(fn ($a) => round($a['de'], 1), $areas), 'backgroundColor' => self::COR['cinza'], 'borderRadius' => 6],
                ['label' => Carbon::parse($c['para'])->format('d/m/Y'), 'data' => array_map(fn ($a) => round($a['para'], 1), $areas), 'backgroundColor' => self::COR['primaria'], 'borderRadius' => 6],
            ]],
            'options' => ['maintainAspectRatio' => false, 'scales' => ['y' => ['min' => 0, 'max' => 100]], 'plugins' => ['legend' => ['position' => 'bottom']]],
        ];
    }

    /** Rosca do esforço do plano: quantas ações em cada prazo (30, 60, 90 dias). */
    public function esforcoDoPlano(array $plano): ?array
    {
        $q = array_map('count', $plano['prazos']);
        if (array_sum($q) === 0) {
            return null;
        }

        return [
            'type' => 'doughnut',
            'data' => ['labels' => ['Até 30 dias', 'Até 60 dias', 'Até 90 dias'], 'datasets' => [['data' => array_values($q),
                'backgroundColor' => [self::COR['vermelho'], self::COR['ambar'], self::COR['azul']], 'borderWidth' => 0, 'hoverOffset' => 8]]],
            'options' => ['maintainAspectRatio' => false, 'cutout' => '60%', 'plugins' => ['legend' => ['position' => 'bottom']]],
        ];
    }

    /**
     * Números do mês em cartões coloridos (receita, resultado, frota, atraso...), com variação desde a fotografia anterior.
     *
     * @return list<array{titulo: string, valor: string, delta: ?string, sentido: ?string, cor: string, icone: string, nota: string}>
     */
    public function kpis(array $r): array
    {
        $h = $r['historico'];
        $d = end($h)['dados'] ?? [];
        $a = count($h) > 1 ? ($h[count($h) - 2]['dados'] ?? []) : null;
        $res = fn ($x) => isset($x['receita_mensal']) && (isset($x['custo_manutencao']) || isset($x['custo_operacional']))
            ? $x['receita_mensal'] - ($x['custo_manutencao'] ?? 0) - ($x['custo_operacional'] ?? 0) : null;
        $ind = fn ($k) => collect($r['indicadores'])->firstWhere('chave', $k);
        $brl = fn ($v) => 'R$ '.number_format($v, 0, ',', '.');
        $pct = fn ($v) => number_format($v, 1, ',', '.').'%';

        $defs = [
            ['Receita do mês', $d['receita_mensal'] ?? null, $a['receita_mensal'] ?? null, 'alto', $brl, '#4f46e5', 'banknotes', 'faturamento'],
            ['Resultado do mês', $res($d), $a ? $res($a) : null, 'alto', $brl, '#16a34a', 'chart-bar', 'receita menos custos'],
            ['Disponibilidade', ($x = $ind('disponibilidade_frota')) ? $x['valor'] : null, $x['anterior'] ?? null, 'alto', $pct, '#0ea5e9', 'truck', 'frota pronta para locar'],
            ['Utilização', ($x = $ind('utilizacao_frota')) ? $x['valor'] : null, $x['anterior'] ?? null, 'alto', $pct, '#8b5cf6', 'arrow-trending-up', 'frota gerando receita'],
            ['Valor em atraso', $d['inadimplencia_valor'] ?? null, $a['inadimplencia_valor'] ?? null, 'baixo', $brl, '#e11d48', 'exclamation-triangle', 'faturas vencidas'],
            ['OS corretivas', $d['os_corretivas'] ?? null, $a['os_corretivas'] ?? null, 'baixo', fn ($v) => number_format($v, 0, ',', '.'), '#d97706', 'wrench-screwdriver', 'quebras no mês'],
        ];

        $out = [];
        foreach ($defs as [$titulo, $v, $antes, $melhor, $f, $cor, $icone, $nota]) {
            if ($v === null) {
                continue;
            }
            $delta = $antes === null ? null : $v - $antes;
            $sentido = $delta === null ? null : ($delta == 0 ? 'igual' : (($delta > 0) === ($melhor === 'alto') ? 'melhor' : 'pior'));
            $out[] = ['titulo' => $titulo, 'valor' => $f($v), 'delta' => $delta === null ? null : ($delta > 0 ? '▲ ' : ($delta < 0 ? '▼ ' : '■ ')).$f(abs($delta)),
                'sentido' => $sentido, 'cor' => $cor, 'icone' => $icone, 'nota' => $nota];
        }

        return $out;
    }

    /**
     * Mapa de calor: nota de cada área (linhas) em cada fotografia (colunas), em cores de semáforo.
     *
     * @return ?array{colunas: list<string>, linhas: list<array{rotulo: string, notas: list<?float>}>}
     */
    public function calor(array $r): ?array
    {
        if (count($r['historico']) < 2) {
            return null;
        }

        return ['colunas' => $this->rotulosDatas($r), 'linhas' => array_map(fn ($a) => ['rotulo' => $a['rotulo'], 'notas' => $a['serie_alinhada']], $r['areas'])];
    }

    public static function corDaNotaPublica(?float $nota): string
    {
        return $nota === null ? '#94a3b8' : ($nota >= 80 ? self::FAIXA['Excelente'] : ($nota >= 60 ? self::FAIXA['Bom'] : ($nota >= 35 ? self::FAIXA['Atenção'] : self::FAIXA['Crítico'])));
    }

    private function transparente(string $hex, float $alfa): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return "rgba({$r},{$g},{$b},{$alfa})";
    }

    private function corDaNota(float $nota): string
    {
        return $nota >= 80 ? self::FAIXA['Excelente'] : ($nota >= 60 ? self::FAIXA['Bom'] : ($nota >= 35 ? self::FAIXA['Atenção'] : self::FAIXA['Crítico']));
    }

    /** @return list<string> */
    private function rotulosDatas(array $r): array
    {
        return array_map(fn ($h) => Carbon::parse($h['data'])->format('d/m/y'), $r['historico']);
    }

    private function curto(string $rotulo): string
    {
        return trim(str_replace('Saúde da ', '', str_replace('Saúde ', '', $rotulo)));
    }
}
