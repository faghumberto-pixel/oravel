<?php

namespace App\Dashboards\Blocks\Financeiro;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\AccountReceivable;

class ReceitaPorCategoria extends FinanceiroBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'financeiro.receita_por_categoria';
    }

    public static function title(): string
    {
        return 'Faturamento por categoria';
    }

    public static function subtitle(): string
    {
        return 'Mês, R$ mil';
    }

    public static function size(): int
    {
        return 4;
    }

    public function data(array $config): array
    {
        $porCategoria = AccountReceivable::query()
            ->with('billCategory:id,name')
            ->whereBetween('due_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->get(['bill_category_id', 'amount'])
            ->groupBy(fn ($t) => $t->billCategory?->name ?? 'Sem categoria')
            ->map(fn ($itens) => (float) $itens->sum('amount'))
            ->sortDesc()
            ->take(6);

        return Payload::donut($this->fatias($porCategoria->map(fn ($v) => round($v / 1000, 1))), 'R$ '.number_format($porCategoria->sum() / 1000, 1, ',', '.').' mil', 'no mês');
    }
}
