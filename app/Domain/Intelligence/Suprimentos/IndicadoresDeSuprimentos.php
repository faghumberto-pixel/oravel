<?php

namespace App\Domain\Intelligence\Suprimentos;

use Carbon\Carbon;

/**
 * Das linhas da planilha de Suprimentos aos indicadores, às listas de atenção e à quebra por categoria. Sem banco: recebe arrays
 * (datas como 'Y-m-d') e devolve arrays, para o importador gravar e a tela recalcular por categoria sobre as mesmas linhas.
 *
 * Indicador sem dado suficiente NÃO entra (nem vira zero): o aviso diz o que faltou preencher.
 */
class IndicadoresDeSuprimentos
{
    /**
     * @param  list<array<string, mixed>>  $itens
     * @param  list<array<string, mixed>>  $compras
     * @param  list<array<string, mixed>>  $movs
     * @return array{indicadores: array<string, array<string, mixed>>, listas: array<string, mixed>, avisos: list<string>}
     */
    public function calcular(array $itens, array $compras, array $movs, Carbon $ref, int $periodo): array
    {
        $avisos = [];
        $defs = Catalogo::indicadores();
        $ind = [];
        $put = function (string $chave, float $valor) use (&$ind, $defs) {
            $d = $defs[$chave];
            $ind[$chave] = ['titulo' => $d['titulo'], 'valor' => round($valor, 2), 'unidade' => $d['unidade'], 'area' => Catalogo::AREA, 'ruim' => $d['ruim'], 'bom' => $d['bom']];
        };

        $inicio = $ref->copy()->subDays($periodo)->startOfDay();
        $custo = [];
        foreach ($itens as $i) {
            $custo[$i['codigo']] = (float) $i['custo_unitario'];
        }
        $movsJanela = array_values(array_filter($movs, fn ($m) => Carbon::parse($m['data'])->betweenIncluded($inicio, $ref->copy()->endOfDay())));
        $saidas = array_values(array_filter($movsJanela, fn ($m) => $m['tipo'] === 'saida'));
        $ultimaSaida = $this->ultimaSaidaPorItem($itens, $movs, $ref);
        $diasParado = (int) config('intelligence.suprimentos.dias_parado');
        $temInfoDeSaida = $ultimaSaida !== [];

        // ---- estoque ----
        $valorEstoque = array_sum(array_map(fn ($i) => $i['quantidade'] * $i['custo_unitario'], $itens));
        $put('valor_estoque', $valorEstoque);

        $comMinimo = array_filter($itens, fn ($i) => ($i['estoque_minimo'] ?? 0) > 0);
        if ($comMinimo !== []) {
            $abaixo = array_filter($comMinimo, fn ($i) => $i['quantidade'] < $i['estoque_minimo']);
            $put('itens_abaixo_minimo_pct', count($abaixo) / count($comMinimo) * 100);
        } else {
            $avisos[] = 'Nenhum item tem "Estoque Mínimo" preenchido: o indicador de itens abaixo do mínimo não foi calculado.';
        }

        $parados = $this->parados($itens, $ultimaSaida, $ref, $diasParado);
        if ($temInfoDeSaida) {
            $comEstoque = array_filter($itens, fn ($i) => $i['quantidade'] > 0);
            if ($comEstoque !== []) {
                $put('itens_parados_pct', count($parados) / count($comEstoque) * 100);
            }
            if ($valorEstoque > 0) {
                $put('valor_parado_pct', array_sum(array_column($parados, 'valor')) / $valorEstoque * 100);
            }
        } else {
            $avisos[] = 'Sem "Data da Última Saída" nos itens e sem saídas na aba Movimentações: não dá para saber quais itens estão parados.';
        }

        // ---- consumo (giro e cobertura) ----
        $consumo = 0.0;
        foreach ($saidas as $m) {
            $consumo += $m['quantidade'] * ($custo[$m['codigo_item']] ?? 0);
        }
        if ($consumo > 0 && $valorEstoque > 0) {
            $porDia = $consumo / $periodo;
            $put('giro_estoque', $porDia * 365 / $valorEstoque);
            $put('cobertura_dias', $valorEstoque / $porDia);
        } elseif ($movs === []) {
            $avisos[] = 'Sem a aba Movimentações: giro e cobertura do estoque não foram calculados.';
        } else {
            $avisos[] = 'Nenhuma saída de item conhecido nos últimos '.$periodo.' dias: giro e cobertura não foram calculados.';
        }

        // ---- compras e fornecedores ----
        $comprasJanela = array_values(array_filter($compras, fn ($c) => Carbon::parse($c['data_pedido'])->betweenIncluded($inicio, $ref->copy()->endOfDay())));
        if ($comprasJanela !== []) {
            $chegadas = array_values(array_filter($comprasJanela, fn ($c) => ! empty($c['data_chegada'])));
            if ($chegadas !== []) {
                $noPrazo = count(array_filter($chegadas, fn ($c) => $c['data_chegada'] <= $c['prazo_prometido']));
                $put('entregas_no_prazo_pct', $noPrazo / count($chegadas) * 100);
                $put('atraso_medio_dias', array_sum(array_map(fn ($c) => $this->diasDeAtraso($c['prazo_prometido'], $c['data_chegada']), $chegadas)) / count($chegadas));
            }
            $abertas = array_filter($comprasJanela, fn ($c) => empty($c['data_chegada']));
            if ($abertas !== []) {
                $atrasadas = array_filter($abertas, fn ($c) => $c['prazo_prometido'] < $ref->toDateString());
                $put('pedidos_abertos_atrasados_pct', count($atrasadas) / count($abertas) * 100);
            }
            $put('compras_emergenciais_pct', count(array_filter($comprasJanela, fn ($c) => ! empty($c['emergencial']))) / count($comprasJanela) * 100);
            $totalCompras = array_sum(array_column($comprasJanela, 'valor_total'));
            if ($totalCompras > 0) {
                $porFornecedor = [];
                foreach ($comprasJanela as $c) {
                    $porFornecedor[$c['fornecedor']] = ($porFornecedor[$c['fornecedor']] ?? 0) + $c['valor_total'];
                }
                $put('concentracao_fornecedor_pct', max($porFornecedor) / $totalCompras * 100);
            }
        } elseif ($compras === []) {
            $avisos[] = 'Sem a aba Compras: entregas, atrasos, compras emergenciais e dependência de fornecedor não foram calculados.';
        } else {
            $avisos[] = 'Nenhuma compra nos últimos '.$periodo.' dias: indicadores de compras não foram calculados.';
        }

        // ---- rastreio ----
        if ($saidas !== []) {
            $put('saidas_sem_destino_pct', count(array_filter($saidas, fn ($m) => trim((string) ($m['destino'] ?? '')) === '')) / count($saidas) * 100);
        }

        return [
            'indicadores' => $ind,
            'listas' => $this->listas($itens, $ultimaSaida, $comprasJanela, $saidas, $custo, $ref, $diasParado, $periodo),
            'avisos' => $avisos,
        ];
    }

    /**
     * Listas que dependem só dos itens (e das saídas conhecidas): abaixo do mínimo, parados e quebra por categoria. A tela usa esta
     * mesma função para filtrar por categoria sobre as linhas guardadas.
     *
     * @param  list<array<string, mixed>>  $itens
     * @param  array<string, string>  $ultimaSaida  código => 'Y-m-d'
     * @return array{abaixo_minimo: list<array<string, mixed>>, parados: list<array<string, mixed>>, por_categoria: list<array<string, mixed>>}
     */
    public function listasDeItens(array $itens, array $ultimaSaida, Carbon $ref, int $diasParado, ?string $categoria = null, array $saidasJanela = [], int $periodo = 90): array
    {
        $tam = (int) config('intelligence.suprimentos.tamanho_lista', 15);
        $todos = $itens;
        if ($categoria !== null && $categoria !== '') {
            $itens = array_values(array_filter($itens, fn ($i) => $i['categoria'] === $categoria));
        }

        // Itens a repor: abaixo do mínimo. Sugestão = repor até 'alvo_x_minimo' vezes o mínimo (config); custo estimado = sugestão × custo.
        $alvo = (float) config('intelligence.suprimentos.alvo_x_minimo', 2);
        $abaixo = [];
        $custoReposicao = 0.0;
        foreach ($itens as $i) {
            if (($i['estoque_minimo'] ?? 0) > 0 && $i['quantidade'] < $i['estoque_minimo']) {
                $sugestao = (float) ceil(max(0, $i['estoque_minimo'] * $alvo - $i['quantidade']));
                $custoReposicao += $sugestao * $i['custo_unitario'];
                $abaixo[] = ['codigo' => $i['codigo'], 'descricao' => $i['descricao'], 'categoria' => $i['categoria'], 'unidade' => $i['unidade'] ?? null, 'quantidade' => $i['quantidade'],
                    'estoque_minimo' => $i['estoque_minimo'], 'falta_pct' => round(($i['estoque_minimo'] - $i['quantidade']) / $i['estoque_minimo'] * 100, 1),
                    'sugestao_compra' => $sugestao, 'custo_estimado' => round($sugestao * $i['custo_unitario'], 2)];
            }
        }
        usort($abaixo, fn ($a, $b) => $b['falta_pct'] <=> $a['falta_pct']);
        $totalAbaixo = count($abaixo);

        // Giro por item: consumo do período (saídas × custo), anualizado, sobre o valor em estoque do item. Só itens com saída e estoque.
        $porCodigo = [];
        foreach ($itens as $i) {
            $porCodigo[$i['codigo']] = $i;
        }
        $consumo = [];
        foreach ($saidasJanela as $m) {
            if (isset($porCodigo[$m['codigo_item']])) {
                $consumo[$m['codigo_item']] = ($consumo[$m['codigo_item']] ?? 0) + $m['quantidade'];
            }
        }
        $giro = [];
        $zerados = [];
        foreach ($consumo as $cod => $qtdSaiu) {
            $i = $porCodigo[$cod];
            $valorItem = $i['quantidade'] * $i['custo_unitario'];
            if ($valorItem > 0) {
                $giro[] = ['codigo' => $cod, 'descricao' => $i['descricao'], 'categoria' => $i['categoria'], 'saiu' => $qtdSaiu, 'quantidade' => $i['quantidade'],
                    'giro_ano' => round(($qtdSaiu * $i['custo_unitario'] / $periodo * 365) / $valorItem, 1)];
            } elseif ($i['quantidade'] <= 0) {
                $zerados[] = ['codigo' => $cod, 'descricao' => $i['descricao'], 'categoria' => $i['categoria'], 'saiu' => $qtdSaiu];
            }
        }
        usort($giro, fn ($a, $b) => $b['giro_ano'] <=> $a['giro_ano']);
        $maiorGiro = array_slice($giro, 0, $tam);
        $menorGiro = array_slice(array_reverse($giro), 0, $tam);
        usort($zerados, fn ($a, $b) => $b['saiu'] <=> $a['saiu']);

        $parados = $this->parados($itens, $ultimaSaida, $ref, $diasParado);
        usort($parados, fn ($a, $b) => $b['valor'] <=> $a['valor']);

        return [
            'abaixo_minimo' => array_slice($abaixo, 0, $tam),
            'abaixo_minimo_total' => $totalAbaixo,
            'custo_reposicao' => round($custoReposicao, 2),
            'parados' => array_slice($parados, 0, $tam),
            'parados_total' => count($parados),
            'parados_valor' => round(array_sum(array_column($parados, 'valor')), 2),
            'maior_giro' => $maiorGiro,
            'menor_giro' => $menorGiro,
            'zerados_com_saida' => array_slice($zerados, 0, $tam),
            'por_categoria' => $this->porCategoria($todos, $ultimaSaida, $ref, $diasParado),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function listas(array $itens, array $ultimaSaida, array $comprasJanela, array $saidas, array $custo, Carbon $ref, int $diasParado, int $periodo): array
    {
        $listas = $this->listasDeItens($itens, $ultimaSaida, $ref, $diasParado, null, $saidas, $periodo);

        // fornecedores
        $porFornecedor = [];
        foreach ($comprasJanela as $c) {
            $f = &$porFornecedor[$c['fornecedor']];
            $f ??= ['fornecedor' => $c['fornecedor'], 'pedidos' => 0, 'valor' => 0.0, 'chegadas' => 0, 'no_prazo' => 0, 'atraso_soma' => 0];
            $f['pedidos']++;
            $f['valor'] += $c['valor_total'];
            if (! empty($c['data_chegada'])) {
                $f['chegadas']++;
                $f['no_prazo'] += $c['data_chegada'] <= $c['prazo_prometido'] ? 1 : 0;
                $f['atraso_soma'] += $this->diasDeAtraso($c['prazo_prometido'], $c['data_chegada']);
            }
            unset($f);
        }
        $fornecedores = array_map(fn ($f) => ['fornecedor' => $f['fornecedor'], 'pedidos' => $f['pedidos'], 'valor' => round($f['valor'], 2),
            'no_prazo_pct' => $f['chegadas'] ? round($f['no_prazo'] / $f['chegadas'] * 100, 1) : null,
            'atraso_medio_dias' => $f['chegadas'] ? round($f['atraso_soma'] / $f['chegadas'], 1) : null], array_values($porFornecedor));
        usort($fornecedores, fn ($a, $b) => $b['valor'] <=> $a['valor']);

        // para onde foi (saídas por destino, em valor)
        $porDestino = [];
        foreach ($saidas as $m) {
            $d = trim((string) ($m['destino'] ?? '')) ?: 'Sem destino';
            $porDestino[$d] ??= ['destino' => $d, 'saidas' => 0, 'valor' => 0.0];
            $porDestino[$d]['saidas']++;
            $porDestino[$d]['valor'] += $m['quantidade'] * ($custo[$m['codigo_item']] ?? 0);
        }
        $totalDestino = array_sum(array_column($porDestino, 'valor'));
        $destinos = array_map(fn ($d) => $d + ['pct' => $totalDestino > 0 ? round($d['valor'] / $totalDestino * 100, 1) : 0.0], array_values($porDestino));
        usort($destinos, fn ($a, $b) => $b['valor'] <=> $a['valor']);

        // pedidos em aberto e atrasados
        $atrasados = [];
        foreach ($comprasJanela as $c) {
            if (empty($c['data_chegada']) && $c['prazo_prometido'] < $ref->toDateString()) {
                $atrasados[] = ['pedido' => $c['pedido'] ?? null, 'codigo_item' => $c['codigo_item'], 'fornecedor' => $c['fornecedor'],
                    'dias_atraso' => $this->diasDeAtraso($c['prazo_prometido'], $ref->toDateString()), 'valor' => round($c['valor_total'], 2)];
            }
        }
        usort($atrasados, fn ($a, $b) => $b['dias_atraso'] <=> $a['dias_atraso']);

        $tam = (int) config('intelligence.suprimentos.tamanho_lista', 15);

        // itens comprados às pressas (mais de uma vez primeiro)
        $emerg = [];
        $nomes = array_column($itens, 'descricao', 'codigo');
        foreach ($comprasJanela as $c) {
            if (! empty($c['emergencial'])) {
                $e = &$emerg[$c['codigo_item']];
                $e ??= ['codigo' => $c['codigo_item'], 'descricao' => $nomes[$c['codigo_item']] ?? $c['codigo_item'], 'compras' => 0, 'valor' => 0.0];
                $e['compras']++;
                $e['valor'] += $c['valor_total'];
                unset($e);
            }
        }
        $emerg = array_map(fn ($e) => $e + ['valor_r' => round($e['valor'], 2)], array_values($emerg));
        usort($emerg, fn ($a, $b) => [$b['compras'], $b['valor']] <=> [$a['compras'], $a['valor']]);

        return $listas + [
            'emergenciais' => array_map(fn ($e) => ['codigo' => $e['codigo'], 'descricao' => $e['descricao'], 'compras' => $e['compras'], 'valor' => $e['valor_r']], array_slice($emerg, 0, 10)),
            'fornecedores' => array_slice($fornecedores, 0, 10),
            'destinos' => array_slice($destinos, 0, 10),
            'pedidos_atrasados' => array_slice($atrasados, 0, $tam),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function porCategoria(array $itens, array $ultimaSaida, Carbon $ref, int $diasParado): array
    {
        $valorTotal = array_sum(array_map(fn ($i) => $i['quantidade'] * $i['custo_unitario'], $itens));
        $grupos = [];
        foreach ($itens as $i) {
            $grupos[$i['categoria']][] = $i;
        }
        $out = [];
        foreach ($grupos as $cat => $lista) {
            $valor = array_sum(array_map(fn ($i) => $i['quantidade'] * $i['custo_unitario'], $lista));
            $parados = $this->parados($lista, $ultimaSaida, $ref, $diasParado);
            $out[] = [
                'categoria' => $cat, 'itens' => count($lista), 'valor' => round($valor, 2), 'pct_valor' => $valorTotal > 0 ? round($valor / $valorTotal * 100, 1) : 0.0,
                'abaixo_minimo' => count(array_filter($lista, fn ($i) => ($i['estoque_minimo'] ?? 0) > 0 && $i['quantidade'] < $i['estoque_minimo'])),
                'parados' => count($parados), 'valor_parado' => round(array_sum(array_column($parados, 'valor')), 2),
            ];
        }
        usort($out, fn ($a, $b) => $b['valor'] <=> $a['valor']);

        return $out;
    }

    /**
     * Itens com estoque e sem saída há mais de $diasParado dias (ou que nunca saíram, se há alguma informação de saída na carga).
     *
     * @return list<array<string, mixed>>
     */
    private function parados(array $itens, array $ultimaSaida, Carbon $ref, int $diasParado): array
    {
        if ($ultimaSaida === []) {
            return []; // sem nenhuma informação de saída não dá para dizer que um item está parado
        }
        $out = [];
        foreach ($itens as $i) {
            if ($i['quantidade'] <= 0) {
                continue;
            }
            $ult = $ultimaSaida[$i['codigo']] ?? null;
            $dias = $ult ? (int) Carbon::parse($ult)->diffInDays($ref, false) : null;
            if ($ult === null || $dias > $diasParado) {
                $out[] = ['codigo' => $i['codigo'], 'descricao' => $i['descricao'], 'categoria' => $i['categoria'], 'quantidade' => $i['quantidade'],
                    'valor' => round($i['quantidade'] * $i['custo_unitario'], 2), 'dias_sem_saida' => $dias];
            }
        }

        return $out;
    }

    /**
     * Última saída conhecida de cada item: a maior entre a data informada no inventário e as saídas da aba Movimentações (até a referência).
     *
     * @return array<string, string> código => 'Y-m-d'
     */
    public function ultimaSaidaPorItem(array $itens, array $movs, Carbon $ref): array
    {
        $ult = [];
        foreach ($itens as $i) {
            if (! empty($i['ultima_saida'])) {
                $ult[$i['codigo']] = $i['ultima_saida'];
            }
        }
        foreach ($movs as $m) {
            if ($m['tipo'] === 'saida' && $m['data'] <= $ref->toDateString() && (! isset($ult[$m['codigo_item']]) || $m['data'] > $ult[$m['codigo_item']])) {
                $ult[$m['codigo_item']] = $m['data'];
            }
        }

        return $ult;
    }

    private function diasDeAtraso(string $prazo, string $chegada): int
    {
        return max(0, (int) Carbon::parse($prazo)->diffInDays(Carbon::parse($chegada), false));
    }
}
