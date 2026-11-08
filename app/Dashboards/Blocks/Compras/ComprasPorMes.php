<?php

namespace App\Dashboards\Blocks\Compras;

use App\Dashboards\Block;
use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\PurchaseOrder;

class ComprasPorMes extends Block
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'compras.por_mes';
    }

    public static function title(): string
    {
        return 'Compras por mês';
    }

    public static function subtitle(): string
    {
        return 'Valor dos pedidos, últimos 12 meses';
    }

    public static function size(): int
    {
        return 6;
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
        $meses = collect(range(11, 0))->map(fn ($i) => now()->startOfMonth()->subMonths($i));
        $serie = $this->serieMensal(PurchaseOrder::query()->where('status', '!=', PurchaseOrder::STATUS_CANCELADA), 'created_at', 12, 'total_value');

        return Payload::line($meses->map(fn ($m) => $m->translatedFormat('M'))->all(), $serie);
    }
}
