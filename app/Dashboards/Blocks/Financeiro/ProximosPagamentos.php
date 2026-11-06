<?php

namespace App\Dashboards\Blocks\Financeiro;

use App\Dashboards\Payload;
use App\Models\AccountPayable;

class ProximosPagamentos extends FinanceiroBlock
{
    public static function key(): string
    {
        return 'financeiro.proximos_pagamentos';
    }

    public static function title(): string
    {
        return 'Próximos pagamentos';
    }

    public static function subtitle(): string
    {
        return 'Vencem nos próximos 7 dias';
    }

    public static function size(): int
    {
        return 6;
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
        $contas = $this->emAberto(AccountPayable::query())
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->orderBy('due_date')
            ->limit(6)
            ->get();

        $eventos = $contas->map(function ($c) {
            $hoje = $c->due_date->isToday();

            return [$hoje ? 'Hoje' : $c->due_date->format('d/m'), $c->description, $this->brl((float) $c->amount), $hoje ? 'crit' : 'warn', $hoje ? 'vence hoje' : null];
        })->all();

        return Payload::timeline($eventos, 'Nenhum pagamento nos próximos 7 dias.');
    }
}
