<?php

namespace App\Dashboards\Blocks\Ativos;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\Asset;

class ValorPorCategoria extends AtivosBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'ativos.valor_por_categoria';
    }

    public static function title(): string
    {
        return 'Patrimônio por categoria';
    }

    public static function subtitle(): string
    {
        return 'Valor de aquisição de cada grupo de equipamentos';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $porCategoria = Asset::query()->with('category:id,name')->get(['asset_category_id', 'acquisition_value'])
            ->groupBy(fn ($a) => $a->category?->name ?? 'Sem categoria')->map(fn ($g) => (float) $g->sum('acquisition_value'))
            ->filter(fn ($v) => $v > 0)->sortDesc()->take(6);
        $total = (float) $porCategoria->sum();

        return Payload::donut($this->fatias($porCategoria->map(fn ($v) => round($v / 1000, 1))), $total >= 1_000_000 ? number_format($total / 1_000_000, 1, ',', '.').' mi' : number_format($total / 1000, 0, ',', '.').' mil', 'em R$');
    }
}
