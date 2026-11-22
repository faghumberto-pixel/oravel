<?php

namespace App\Domain\Intelligence\Services;

use App\Domain\Intelligence\Support\Catalogo;

/**
 * Do resumo (EvolucaoService) para as duas telas de ação: Diagnóstico (pontos de atenção e pontos fortes) e Plano de evolução
 * (o que fazer, em que ordem e até onde chegar). Puro: só lê o resumo e o catálogo, sem banco. A meta final de cada indicador é o
 * valor que vale 100 pontos no catálogo; a meta do ciclo é o meio do caminho entre hoje e ela (alcançável até a próxima importação).
 */
class PlanoDeEvolucao
{
    public const LIMITE_ATENCAO = 60;

    public const LIMITE_FORTE = 80;

    /**
     * @return array{atencao: list<array>, fortes: list<array>, areas: list<array>}
     */
    public function diagnostico(array $r): array
    {
        $itens = $this->pontuaveis($r);
        $atencao = array_values(array_filter($itens, fn ($i) => $i['nota'] < self::LIMITE_ATENCAO));
        $fortes = array_values(array_filter($itens, fn ($i) => $i['nota'] >= self::LIMITE_FORTE));
        usort($fortes, fn ($a, $b) => $b['nota'] <=> $a['nota']);

        $areas = array_values(array_filter($r['areas'], fn ($a) => $a['chave'] !== 'geral'));
        usort($areas, fn ($a, $b) => $a['nota'] <=> $b['nota']);

        return ['atencao' => array_slice($atencao, 0, 8), 'fortes' => array_slice($fortes, 0, 4), 'areas' => $areas];
    }

    /**
     * @return array{foco: ?string, itens: list<array>, prazos: array<int, list<array>>}
     */
    public function plano(array $r): array
    {
        $itens = array_values(array_filter($this->pontuaveis($r), fn ($i) => $i['nota'] < self::LIMITE_FORTE));
        $itens = array_slice($itens, 0, 8);

        foreach ($itens as &$i) {
            $i['prioridade'] = $i['nota'] < 35 ? 'Alta' : ($i['nota'] < self::LIMITE_ATENCAO ? 'Média' : 'Baixa');
            $i['prazo'] = ['Alta' => 30, 'Média' => 60, 'Baixa' => 90][$i['prioridade']];
            $i['alvo_ciclo'] = round($i['valor'] + ($i['meta'] - $i['valor']) * 0.5, 1);
        }
        unset($i);

        $areas = array_values(array_filter($r['areas'], fn ($a) => $a['chave'] !== 'geral'));
        usort($areas, fn ($a, $b) => $a['nota'] <=> $b['nota']);

        $prazos = [30 => [], 60 => [], 90 => []];
        foreach ($itens as $i) {
            $prazos[$i['prazo']][] = $i;
        }

        return ['foco' => $areas[0]['rotulo'] ?? null, 'itens' => $itens, 'prazos' => $prazos];
    }

    /** Indicadores com nota, do pior para o melhor, já com meta, área e orientação. */
    private function pontuaveis(array $r): array
    {
        $cat = Catalogo::indicadores();
        $orient = Catalogo::orientacao();
        $areas = config('intelligence.areas');
        $out = [];

        foreach ($r['indicadores'] as $i) {
            if ($i['nota'] === null || ! isset($cat[$i['chave']]['bom'])) {
                continue;
            }
            $out[] = $i + [
                'meta' => (float) $cat[$i['chave']]['bom'],
                'area_rotulo' => $areas[$i['area']] ?? $i['area'],
                'impacto' => $orient[$i['chave']]['impacto'] ?? '',
                'acoes' => $orient[$i['chave']]['acoes'] ?? [],
                'faixa' => $i['nota'] >= 80 ? 'Excelente' : ($i['nota'] >= 60 ? 'Bom' : ($i['nota'] >= 35 ? 'Atenção' : 'Crítico')),
            ];
        }
        usort($out, fn ($a, $b) => $a['nota'] <=> $b['nota']);

        return $out;
    }
}
