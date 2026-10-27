<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\Contract;

class ContratosPorMes extends ComercialBlock
{
    public static function key(): string
    {
        return 'comercial.contratos_por_mes';
    }

    public static function title(): string
    {
        return 'Contratos por mês';
    }

    public static function subtitle(): string
    {
        return 'Novos x encerrados, últimos 6 meses';
    }

    public static function size(): int
    {
        return 12;
    }

    public function data(array $config): array
    {
        $inicio = now()->startOfMonth()->subMonths(5);

        $novos = Contract::query()->where('start_date', '>=', $inicio->toDateString())->get(['start_date']);
        $encerrados = Contract::query()->where('status', 'Encerrado')->where('end_date', '>=', $inicio->toDateString())->get(['end_date']);

        $labels = [];
        $a = [];
        $b = [];
        for ($i = 0; $i < 6; $i++) {
            $mes = $inicio->copy()->addMonths($i);
            $labels[] = ucfirst($mes->translatedFormat('M'));
            $a[] = $novos->filter(fn ($c) => $c->start_date->isSameMonth($mes))->count();
            $b[] = $encerrados->filter(fn ($c) => $c->end_date->isSameMonth($mes))->count();
        }

        return Payload::cols($labels, $a, $b, 'Novos contratos', 'Encerrados');
    }
}
