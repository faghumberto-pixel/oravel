<?php

namespace App\Dashboards\Blocks\Financeiro;

use App\Dashboards\Payload;
use App\Models\AccountPayable;

class PagamentosAtrasados extends FinanceiroBlock
{
    public static function key(): string
    {
        return 'financeiro.pagamentos_atrasados';
    }

    public static function title(): string
    {
        return 'Pagamentos em atraso';
    }

    public static function subtitle(): string
    {
        return 'Mais antigos primeiro';
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
        $contas = $this->atrasadas(AccountPayable::query())->with('billCategory:id,name')->orderBy('due_date')->limit(6)->get();

        $linhas = $contas->map(function ($c) {
            $dias = (int) $c->due_date->diffInDays(now());

            return [$c->description, $c->billCategory?->name ?? '—', Payload::pill($dias > 15 ? 'crit' : 'warn', $dias.' dias'), $this->brl((float) $c->amount)];
        })->all();

        return Payload::table(['Conta', 'Categoria', 'Atraso', '>Valor'], $linhas, 'Nenhuma conta em atraso.');
    }
}
