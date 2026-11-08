<?php

namespace App\Dashboards\Blocks\Compras;

use App\Dashboards\Block;
use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\PurchaseOrder;

class ComprasPorFornecedor extends Block
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'compras.por_fornecedor';
    }

    public static function title(): string
    {
        return 'Compras por fornecedor';
    }

    public static function subtitle(): string
    {
        return 'Valor dos pedidos nos últimos 90 dias';
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
        $linhas = PurchaseOrder::query()->with('supplier:id,name')->where('status', '!=', PurchaseOrder::STATUS_CANCELADA)->where('created_at', '>=', now()->subDays(90))->get()
            ->groupBy(fn (PurchaseOrder $p) => $p->supplier?->name ?? 'Sem fornecedor')->map(fn ($g) => round((float) $g->sum('total_value'), 2))->sortDesc()->take(7);

        return Payload::ranking($this->linhasDeRanking($linhas, fn ($v) => 'R$ '.number_format($v, 2, ',', '.')), [], 'Pedidos não cancelados, por data de criação');
    }
}
