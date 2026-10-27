<?php

namespace App\Dashboards\Blocks\Financeiro;

use App\Dashboards\Payload;
use App\Models\AccountPayable;

class APagarNoMes extends FinanceiroBlock
{
    public static function key(): string
    {
        return 'financeiro.a_pagar_mes';
    }

    public static function title(): string
    {
        return 'A pagar no mês';
    }

    public static function subtitle(): string
    {
        return 'Vencimentos do mês ainda em aberto';
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
        $q = $this->emAberto(AccountPayable::query())->whereBetween('due_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);
        $n = (clone $q)->count();

        return Payload::kpi($this->brl((float) (clone $q)->sum('amount')), '', $n.' '.($n === 1 ? 'conta' : 'contas'));
    }
}
