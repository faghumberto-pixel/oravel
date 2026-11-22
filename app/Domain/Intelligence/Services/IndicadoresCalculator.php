<?php

namespace App\Domain\Intelligence\Services;

use App\Domain\Intelligence\Support\Catalogo;

/** Dados (campos da planilha) → indicadores. Indicador sem dados suficientes é omitido, nunca inventado. */
class IndicadoresCalculator
{
    /**
     * @param  array<string, mixed>  $dados
     * @return array<string, array{valor: float, unidade: string, area: string, ruim: ?float, bom: ?float}>
     */
    public function calcular(array $dados): array
    {
        $out = [];
        foreach (Catalogo::indicadores() as $chave => $def) {
            $v = ($def['calc'])($dados);
            if ($v === null) {
                continue;
            }
            $out[$chave] = [
                'valor' => round((float) $v, 4), 'unidade' => $def['unidade'], 'area' => $def['area'],
                'ruim' => $def['ruim'] === null ? null : (float) $def['ruim'], 'bom' => $def['bom'] === null ? null : (float) $def['bom'],
            ];
        }

        return $out;
    }
}
