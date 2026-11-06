<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\Contract;

class ContratosAtivos extends ComercialBlock
{
    public static function key(): string
    {
        return 'comercial.contratos_ativos';
    }

    public static function title(): string
    {
        return 'Contratos ativos';
    }

    public static function subtitle(): string
    {
        return 'Locações em vigor';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $ativos = Contract::query()->where('status', 'Ativo');
        $total = (clone $ativos)->count();
        $mensal = (float) (clone $ativos)->sum('price');

        $inicio = now()->startOfMonth()->subMonths(11);
        $porMes = Contract::query()->whereNotNull('start_date')->where('start_date', '>=', $inicio)->get(['start_date'])->groupBy(fn ($c) => $c->start_date->format('Y-m'))->map->count();
        $serie = collect(range(0, 11))->map(fn ($i) => (int) ($porMes[$inicio->copy()->addMonths($i)->format('Y-m')] ?? 0))->all();

        return Payload::kpi($total, $total === 1 ? 'contrato' : 'contratos', $this->brl($mensal).' por período', 'n', null, $serie);
    }
}
