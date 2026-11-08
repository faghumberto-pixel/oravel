<?php

namespace App\Dashboards\Blocks\Compras;

use App\Dashboards\Block;
use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\PurchaseOrder;

class PedidosAtrasados extends Block
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'compras.pedidos_atrasados';
    }

    public static function title(): string
    {
        return 'Pedidos de compra atrasados';
    }

    public static function subtitle(): string
    {
        return 'Entrega prevista já passou e ainda não foi recebida';
    }

    public static function size(): int
    {
        return 4;
    }

    public static function module(): string
    {
        return 'Materiais e Peças';
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
        $q = PurchaseOrder::query()->whereIn('status', [PurchaseOrder::STATUS_ABERTA, PurchaseOrder::STATUS_PARCIALMENTE_RECEBIDA])
            ->whereNotNull('expected_delivery_date')->whereDate('expected_delivery_date', '<', today());
        $linhas = (clone $q)->with('supplier:id,name')->orderBy('expected_delivery_date')->limit(6)->get()->map(fn (PurchaseOrder $p) => [
            $p->supplier?->name ?? '—', $p->expected_delivery_date->format('d/m/Y'),
            Payload::pill('crit', (int) $p->expected_delivery_date->startOfDay()->diffInDays(now()->startOfDay()).' d de atraso'),
        ])->all();

        return Payload::table(['Fornecedor', '>Previsto', '>Atraso'], $linhas, 'Nenhum pedido atrasado.');
    }
}
