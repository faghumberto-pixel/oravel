<?php

namespace App\Dashboards\Blocks\InteligenciaAtivos;

use App\Dashboards\Payload;

class CustoDoPeriodo extends IaBlock
{
    public static function key(): string
    {
        return 'ativos_ia.custo_12m';
    }

    public static function title(): string
    {
        return 'Custo de manutenção (12 meses)';
    }

    public static function subtitle(): string
    {
        return 'Mão de obra, material e logística das OS da frota';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $linhas = $this->ranking();
        $serie = array_fill(0, 12, 0.0);
        foreach ($linhas as $l) {
            foreach ($l['serie'] as $i => $v) {
                $serie[$i] += $v;
            }
        }

        $total = array_sum($serie);
        $recente = array_sum(array_slice($serie, 6));
        $anterior = array_sum(array_slice($serie, 0, 6));
        $delta = '';
        $tom = 'n';
        if ($anterior > 0) {
            $var = (int) round(($recente - $anterior) / $anterior * 100);
            $delta = ($var > 0 ? '↑ ' : '↓ ').abs($var).'% nos últimos 6 meses vs. os 6 anteriores';
            $tom = $var > 0 ? 'dn' : 'up';
        }

        return Payload::kpi($this->compacto($total), '', $delta, $tom, null, array_map(fn ($v) => round($v, 2), $serie));
    }
}
