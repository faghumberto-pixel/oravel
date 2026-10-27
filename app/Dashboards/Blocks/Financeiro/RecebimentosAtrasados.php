<?php

namespace App\Dashboards\Blocks\Financeiro;

use App\Dashboards\Payload;
use App\Models\AccountReceivable;

class RecebimentosAtrasados extends FinanceiroBlock
{
    public static function key(): string
    {
        return 'financeiro.recebimentos_atrasados';
    }

    public static function title(): string
    {
        return 'Recebimentos em atraso';
    }

    public static function subtitle(): string
    {
        return 'Mais antigos primeiro';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $titulos = $this->atrasadas(AccountReceivable::query())
            ->with('client:id,name')
            ->orderBy('due_date')
            ->limit(6)
            ->get();

        $linhas = $titulos->map(function ($t) {
            $dias = (int) $t->due_date->diffInDays(now());

            return [
                $t->client?->name ?? '—',
                $t->description,
                Payload::pill($dias > 15 ? 'crit' : 'warn', $dias.' dias'),
                $this->brl((float) $t->amount),
            ];
        })->all();

        return Payload::table(['Cliente', 'Descrição', 'Atraso', '>Valor'], $linhas, 'Nenhum recebimento em atraso.');
    }
}
