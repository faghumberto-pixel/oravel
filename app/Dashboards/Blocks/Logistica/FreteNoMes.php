<?php

namespace App\Dashboards\Blocks\Logistica;

use App\Dashboards\Payload;
use App\Models\FreightRecord;

class FreteNoMes extends LogisticaBlock
{
    public static function key(): string
    {
        return 'logistica.frete_mes';
    }

    public static function title(): string
    {
        return 'Frete no mês';
    }

    public static function subtitle(): string
    {
        return 'Custo dos fretes lançados';
    }

    public static function size(): int
    {
        return 3;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_freight_records';
    }

    public static function sourceModel(): string
    {
        return FreightRecord::class;
    }

    public function data(array $config): array
    {
        $soma = fn ($de, $ate) => (float) FreightRecord::query()->whereBetween('data', [$de->toDateString(), $ate->toDateString()])->sum('valor');

        $atual = $soma(now()->startOfMonth(), now()->endOfMonth());
        $anterior = $soma(now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth());

        if ($anterior <= 0) {
            return Payload::kpi($this->brl($atual), '', 'Sem mês anterior para comparar');
        }

        $pct = round(($atual - $anterior) / $anterior * 100);

        return Payload::kpi($this->brl($atual), '', ($pct >= 0 ? '+' : '').$pct.'% sobre o mês anterior', $pct <= 0 ? 'up' : 'dn');
    }
}
