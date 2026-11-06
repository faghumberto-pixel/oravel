<?php

namespace App\Dashboards\Blocks\Financeiro;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;

class SaldoDoMes extends FinanceiroBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'financeiro.saldo_mes';
    }

    public static function title(): string
    {
        return 'Saldo do mês';
    }

    public static function subtitle(): string
    {
        return 'Recebido menos pago, com a evolução dos últimos 12 meses';
    }

    public static function size(): int
    {
        return 4;
    }

    public function data(array $config): array
    {
        $r = fn ($m) => (float) $m::query()->where('status', 'pago')->whereBetween('payment_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->sum('amount');
        $recebido = $r(AccountReceivable::class);
        $pago = $r(AccountPayable::class);
        $saldo = $recebido - $pago;
        $a = $this->serieMensal(AccountReceivable::query()->where('status', 'pago'), 'payment_date', 12, 'amount');
        $b = $this->serieMensal(AccountPayable::query()->where('status', 'pago'), 'payment_date', 12, 'amount');
        $serie = array_map(fn ($x, $y) => round($x - $y, 2), $a, $b);
        $pct = $recebido > 0 ? (int) round(min(100, max(0, $saldo / $recebido * 100))) : 0;

        return Payload::kpi($this->brl(abs($saldo)), '', ($saldo >= 0 ? 'Sobra' : 'Falta').' neste mês · recebido '.$this->brl($recebido).' e pago '.$this->brl($pago), $saldo >= 0 ? 'up' : 'dn', ['pct' => $pct, 'tone' => $saldo >= 0 ? 'ok' : 'crit'], $serie);
    }
}
