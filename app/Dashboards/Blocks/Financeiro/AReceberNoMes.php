<?php

namespace App\Dashboards\Blocks\Financeiro;

use App\Dashboards\Payload;
use App\Models\AccountReceivable;

class AReceberNoMes extends FinanceiroBlock
{
    public static function key(): string
    {
        return 'financeiro.a_receber_mes';
    }

    public static function title(): string
    {
        return 'A receber no mês';
    }

    public static function subtitle(): string
    {
        return 'Vencimentos do mês ainda em aberto';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $q = $this->emAberto(AccountReceivable::query())->whereBetween('due_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);
        $n = (clone $q)->count();

        return Payload::kpi($this->brl((float) (clone $q)->sum('amount')), '', $n.' '.($n === 1 ? 'título' : 'títulos'));
    }
}
