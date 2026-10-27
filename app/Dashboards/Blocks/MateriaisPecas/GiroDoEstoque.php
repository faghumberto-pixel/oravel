<?php

namespace App\Dashboards\Blocks\MateriaisPecas;

use App\Dashboards\Payload;
use App\Models\Material;

class GiroDoEstoque extends MateriaisBlock
{
    public static function key(): string
    {
        return 'materiais.giro';
    }

    public static function title(): string
    {
        return 'Giro do estoque';
    }

    public static function subtitle(): string
    {
        return 'Consumo de 12 meses sobre o estoque atual';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $custos = Material::query()->pluck('unit_cost', 'id');
        $consumido = $this->saidasPorMaterial(365)->sum(fn ($q, $id) => $q * (float) ($custos[$id] ?? 0));
        $estoque = (float) Material::query()->get(['unit_cost', 'current_stock'])->sum(fn ($m) => $m->unit_cost * $m->current_stock);

        if ($estoque <= 0 || $consumido <= 0) {
            return Payload::kpi('—', '', 'Sem consumo registrado no período');
        }

        return Payload::kpi(number_format($consumido / $estoque, 1, ',', '.'), 'x', $this->mil($consumido).' consumidos em 12 meses');
    }
}
