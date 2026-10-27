<?php

namespace App\Dashboards\Blocks\MateriaisPecas;

use App\Dashboards\Payload;
use App\Models\PurchaseOrder;

class ComprasAReceber extends MateriaisBlock
{
    public static function key(): string
    {
        return 'materiais.compras_a_receber';
    }

    public static function title(): string
    {
        return 'Compras a receber';
    }

    public static function subtitle(): string
    {
        return 'Pedidos abertos';
    }

    public static function size(): int
    {
        return 3;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_purchase_orders';
    }

    public static function sourceModel(): string
    {
        return PurchaseOrder::class;
    }

    public function data(array $config): array
    {
        $abertos = PurchaseOrder::query()->whereIn('status', $this->situacoesEmAberto());
        $pedidos = (clone $abertos)->count();
        $valor = (float) (clone $abertos)->sum('total_value');

        return Payload::kpi($pedidos, $pedidos === 1 ? 'pedido' : 'pedidos', $this->brl($valor));
    }
}
