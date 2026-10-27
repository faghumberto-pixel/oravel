<?php

namespace App\Dashboards\Blocks\MateriaisPecas;

use App\Dashboards\Payload;
use App\Models\Material;
use App\Models\PurchaseOrderItem;

class ItensAbaixoDoMinimo extends MateriaisBlock
{
    public static function key(): string
    {
        return 'materiais.abaixo_minimo_total';
    }

    public static function title(): string
    {
        return 'Itens abaixo do mínimo';
    }

    public static function subtitle(): string
    {
        return 'Precisam de compra';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $abaixo = Material::query()->where('min_stock', '>', 0)->whereColumn('current_stock', '<', 'min_stock')->pluck('id');

        $comPedido = PurchaseOrderItem::query()
            ->whereIn('material_id', $abaixo)
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', $this->situacoesEmAberto()))
            ->distinct()
            ->pluck('material_id');

        $semPedido = $abaixo->diff($comPedido)->count();

        return Payload::kpi(
            $abaixo->count(),
            'itens',
            $semPedido > 0 ? $semPedido.' sem pedido aberto' : 'Todos com pedido aberto',
            $semPedido > 0 ? 'dn' : 'n',
        );
    }
}
