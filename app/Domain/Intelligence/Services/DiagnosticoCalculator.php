<?php

namespace App\Domain\Intelligence\Services;

/**
 * Indicadores → nota 0–100 por área e índice geral. Cada indicador vale 0 em `ruim` e 100 em `bom` (linear, vale nos dois sentidos).
 * Área = média dos indicadores pontuáveis dela; geral = média das áreas que tiveram nota. Área sem dados fica de fora.
 */
class DiagnosticoCalculator
{
    /**
     * @param  array<string, array{valor: float, area: string, ruim: ?float, bom: ?float}>  $indicadores
     * @return array<string, array{pontuacao: float, faixa: string, detalhes: array<string, float>}>
     */
    public function calcular(array $indicadores): array
    {
        $porArea = [];
        foreach ($indicadores as $chave => $i) {
            if ($i['ruim'] === null || $i['bom'] === null) {
                continue;
            }
            $nota = max(0, min(1, ($i['valor'] - $i['ruim']) / ($i['bom'] - $i['ruim']))) * 100;
            $porArea[$i['area']][$chave] = round($nota, 2);
        }

        $out = [];
        foreach ($porArea as $area => $notas) {
            $p = round(array_sum($notas) / count($notas), 2);
            $out[$area] = ['pontuacao' => $p, 'faixa' => $this->faixa($p), 'detalhes' => $notas];
        }

        if ($out !== []) {
            $geral = round(array_sum(array_column($out, 'pontuacao')) / count($out), 2);
            $out['geral'] = ['pontuacao' => $geral, 'faixa' => $this->faixa($geral), 'detalhes' => array_map(fn ($a) => $a['pontuacao'], $out)];
        }

        return $out;
    }

    public function faixa(float $pontuacao): string
    {
        return match (true) {
            $pontuacao >= 80 => 'Excelente',
            $pontuacao >= 60 => 'Bom',
            $pontuacao >= 35 => 'Atenção',
            default => 'Crítico',
        };
    }
}
