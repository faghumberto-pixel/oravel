<?php

namespace App\Dashboards\Blocks\MateriaisPecas;

use App\Dashboards\Payload;
use App\Models\Material;

class ValorEmEstoque extends MateriaisBlock
{
    public static function key(): string
    {
        return 'materiais.valor_em_estoque';
    }

    public static function title(): string
    {
        return 'Valor em estoque';
    }

    public static function subtitle(): string
    {
        return 'Materiais e peças, a custo';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $itens = Material::query()->where('current_stock', '>', 0)->get(['unit_cost', 'current_stock']);
        $valor = (float) $itens->sum(fn ($m) => $m->unit_cost * $m->current_stock);

        return Payload::kpi($this->mil($valor), '', $itens->count().' itens com saldo');
    }
}
