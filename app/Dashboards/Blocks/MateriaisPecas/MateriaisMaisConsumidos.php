<?php

namespace App\Dashboards\Blocks\MateriaisPecas;

use App\Dashboards\Payload;
use App\Models\Material;

class MateriaisMaisConsumidos extends MateriaisBlock
{
    public static function key(): string
    {
        return 'materiais.mais_consumidos';
    }

    public static function title(): string
    {
        return 'Materiais mais consumidos';
    }

    public static function subtitle(): string
    {
        return 'Saídas do estoque, últimos 30 dias';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $top = $this->saidasPorMaterial(30)->sortDesc()->take(5);
        $nomes = Material::query()->whereIn('id', $top->keys())->pluck('name', 'id');

        return Payload::bars($top->map(fn ($q, $id) => [$nomes[$id] ?? 'Material removido', (float) $q, rtrim(rtrim(number_format($q, 2, ',', '.'), '0'), ',')])->values()->all());
    }
}
