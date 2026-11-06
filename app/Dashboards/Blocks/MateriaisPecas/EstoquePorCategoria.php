<?php

namespace App\Dashboards\Blocks\MateriaisPecas;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\Material;

class EstoquePorCategoria extends MateriaisBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'materiais.estoque_por_categoria';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function title(): string
    {
        return 'Estoque por categoria';
    }

    public static function subtitle(): string
    {
        return 'Valor, R$ mil';
    }

    public function data(array $config): array
    {
        $porCategoria = Material::query()
            ->with('category:id,name')
            ->where('current_stock', '>', 0)
            ->get(['material_category_id', 'unit_cost', 'current_stock'])
            ->groupBy(fn ($m) => $m->category?->name ?? 'Sem categoria')
            ->map(fn ($itens) => (float) $itens->sum(fn ($m) => $m->unit_cost * $m->current_stock))
            ->sortDesc()
            ->take(7);

        return Payload::donut($this->fatias($porCategoria->map(fn ($v) => round($v / 1000, 1))), $this->mil((float) $porCategoria->sum()), 'em estoque');
    }
}
