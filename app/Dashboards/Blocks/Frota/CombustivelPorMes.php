<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;

class CombustivelPorMes extends FrotaBlock
{
    public static function key(): string
    {
        return 'frota.combustivel_por_mes';
    }

    public static function title(): string
    {
        return 'Combustível por mês';
    }

    public static function subtitle(): string
    {
        return 'Últimos 6 meses, R$ mil';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $labels = [];
        $valores = [];
        for ($i = 5; $i >= 0; $i--) {
            $mes = now()->startOfMonth()->subMonths($i);
            $labels[] = ucfirst($mes->translatedFormat('M'));
            $valores[] = round($this->gastoComCombustivel($mes, $mes->copy()->endOfMonth()) / 1000, 1);
        }

        return Payload::line($labels, $valores);
    }
}
