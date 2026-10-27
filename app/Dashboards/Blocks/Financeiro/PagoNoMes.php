<?php

namespace App\Dashboards\Blocks\Financeiro;

use App\Dashboards\Payload;
use App\Models\AccountPayable;

class PagoNoMes extends FinanceiroBlock
{
    public static function key(): string
    {
        return 'financeiro.pago_mes';
    }

    public static function title(): string
    {
        return 'Pago no mês';
    }

    public static function subtitle(): string
    {
        return 'Contas quitadas';
    }

    public static function size(): int
    {
        return 3;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_account_payables';
    }

    public static function sourceModel(): string
    {
        return AccountPayable::class;
    }

    public function data(array $config): array
    {
        $soma = fn ($de, $ate) => (float) AccountPayable::query()->where('status', 'pago')->whereBetween('payment_date', [$de->toDateString(), $ate->toDateString()])->sum('amount');

        $atual = $soma(now()->startOfMonth(), now()->endOfMonth());
        $anterior = $soma(now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth());

        if ($anterior <= 0) {
            return Payload::kpi($this->brl($atual), '', 'Sem mês anterior para comparar');
        }

        $pct = round(($atual - $anterior) / $anterior * 100);

        // gastar menos que no mês anterior é a variação "boa"
        return Payload::kpi($this->brl($atual), '', ($pct >= 0 ? '+' : '').$pct.'% sobre o mês anterior', $pct <= 0 ? 'up' : 'dn');
    }
}
