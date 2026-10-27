<?php

namespace App\Dashboards\Blocks\Financeiro;

use App\Dashboards\Payload;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;

class RecebidoXPago extends FinanceiroBlock
{
    public static function key(): string
    {
        return 'financeiro.recebido_x_pago';
    }

    public static function title(): string
    {
        return 'Recebido x pago por mês';
    }

    public static function subtitle(): string
    {
        return 'Últimos 6 meses, R$ mil';
    }

    public static function size(): int
    {
        return 12;
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
        $inicio = now()->startOfMonth()->subMonths(5);

        $recebidos = AccountReceivable::query()->where('status', 'pago')->where('payment_date', '>=', $inicio->toDateString())->get(['amount', 'payment_date']);
        $pagos = AccountPayable::query()->where('status', 'pago')->where('payment_date', '>=', $inicio->toDateString())->get(['amount', 'payment_date']);

        $labels = [];
        $a = [];
        $b = [];
        for ($i = 0; $i < 6; $i++) {
            $mes = $inicio->copy()->addMonths($i);
            $labels[] = ucfirst($mes->translatedFormat('M'));
            $a[] = round($recebidos->filter(fn ($x) => $x->payment_date->isSameMonth($mes))->sum('amount') / 1000, 1);
            $b[] = round($pagos->filter(fn ($x) => $x->payment_date->isSameMonth($mes))->sum('amount') / 1000, 1);
        }

        return Payload::cols($labels, $a, $b, 'Recebido', 'Pago');
    }
}
