<?php

namespace App\Dashboards\Blocks\Financeiro;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\AccountPayable;

class PagarPorCategoria extends FinanceiroBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'financeiro.pagar_por_categoria';
    }

    public static function title(): string
    {
        return 'Para onde vai o dinheiro';
    }

    public static function subtitle(): string
    {
        return 'Contas a pagar do mês por categoria';
    }

    public static function size(): int
    {
        return 4;
    }

    public function data(array $config): array
    {
        $porCategoria = AccountPayable::query()->with('billCategory:id,name')
            ->whereBetween('due_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->get(['bill_category_id', 'amount'])
            ->groupBy(fn ($t) => $t->billCategory?->name ?? 'Sem categoria')->map(fn ($g) => (float) $g->sum('amount'))->sortDesc()->take(6);

        return Payload::donut($this->fatias($porCategoria->map(fn ($v) => round($v / 1000, 1))), 'R$ '.number_format($porCategoria->sum() / 1000, 1, ',', '.').' mil', 'no mês');
    }
}
