<?php

namespace App\Dashboards\Blocks\MateriaisPecas;

use App\Dashboards\Payload;
use App\Models\Material;

class EstoquePorCategoria extends MateriaisBlock
{
    public static function key(): string
    {
        return 'materiais.estoque_por_categoria';
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

        return Payload::bars($porCategoria->map(fn ($v, $nome) => [$nome, round($v / 1000, 1), number_format($v / 1000, 1, ',', '.')])->values()->all());
    }
}
