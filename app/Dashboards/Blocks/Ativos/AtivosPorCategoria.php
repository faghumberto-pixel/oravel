<?php

namespace App\Dashboards\Blocks\Ativos;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\Asset;

class AtivosPorCategoria extends AtivosBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'ativos.por_categoria';
    }

    public static function title(): string
    {
        return 'Ativos por categoria';
    }

    public static function subtitle(): string
    {
        return 'Maiores categorias';
    }

    public static function size(): int
    {
        return 4;
    }

    public function data(array $config): array
    {
        $porCategoria = Asset::query()
            ->with('category:id,name')
            ->get(['asset_category_id'])
            ->groupBy(fn ($a) => $a->category?->name ?? 'Sem categoria')
            ->map->count()
            ->sortDesc()
            ->take(6);

        return Payload::ranking($this->linhasDeRanking($porCategoria, fn ($n) => $n.' '.($n === 1 ? 'ativo' : 'ativos')));
    }
}
