<?php

namespace App\Dashboards\Blocks\Financeiro;

use App\Dashboards\Payload;
use App\Models\AccountReceivable;

class ReceitaPorCategoria extends FinanceiroBlock
{
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
        return 6;
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

        return Payload::bars($porCategoria->map(fn ($v, $nome) => [$nome, round($v / 1000, 1), number_format($v / 1000, 1, ',', '.')])->values()->all());
    }
}
