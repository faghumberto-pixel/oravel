<?php

namespace App\Dashboards\Blocks\Financeiro;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\AccountReceivable;

class RecebidoNoMes extends FinanceiroBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'financeiro.recebido_mes';
    }

    public static function title(): string
    {
        return 'Recebido no mês';
    }

    public static function subtitle(): string
    {
        return 'Títulos pagos pelos clientes';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $soma = fn ($de, $ate) => (float) AccountReceivable::query()->where('status', 'pago')->whereBetween('payment_date', [$de->toDateString(), $ate->toDateString()])->sum('amount');

        $atual = $soma(now()->startOfMonth(), now()->endOfMonth());
        $anterior = $soma(now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth());

        $serie = $this->serieMensal(AccountReceivable::query()->where('status', 'pago'), 'payment_date', 12, 'amount');
        if ($anterior <= 0) {
            return Payload::kpi($this->brl($atual), '', 'Sem mês anterior para comparar', 'n', null, $serie);
        }

        $pct = round(($atual - $anterior) / $anterior * 100);

        return Payload::kpi($this->brl($atual), '', ($pct >= 0 ? '+' : '').$pct.'% sobre o mês anterior', $pct >= 0 ? 'up' : 'dn', null, $serie);
    }
}
