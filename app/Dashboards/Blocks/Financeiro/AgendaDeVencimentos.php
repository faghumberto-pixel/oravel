<?php

namespace App\Dashboards\Blocks\Financeiro;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;

class AgendaDeVencimentos extends FinanceiroBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'financeiro.agenda_vencimentos';
    }

    public static function title(): string
    {
        return 'Agenda da semana';
    }

    public static function subtitle(): string
    {
        return 'Contas a pagar e a receber dos próximos 7 dias, na ordem em que vencem';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $de = now()->toDateString();
        $ate = now()->addDays(7)->toDateString();
        $pagar = $this->emAberto(AccountPayable::query())->whereBetween('due_date', [$de, $ate])->orderBy('due_date')->limit(6)->get();
        $receber = $this->emAberto(AccountReceivable::query())->whereBetween('due_date', [$de, $ate])->orderBy('due_date')->limit(6)->get();

        $eventos = [];
        foreach ($pagar as $c) {
            $eventos[] = [$c->due_date, 'Pagar — '.$c->description, $this->brl((float) $c->amount), 'crit', 'saída'];
        }
        foreach ($receber as $c) {
            $eventos[] = [$c->due_date, 'Receber — '.($c->description ?? 'título'), $this->brl((float) $c->amount), 'ok', 'entrada'];
        }
        usort($eventos, fn ($a, $b) => $a[0] <=> $b[0]);

        return Payload::timeline(array_map(fn ($e) => [$e[0]->isToday() ? 'Hoje' : $e[0]->format('d/m'), $e[1], $e[2], $e[3], $e[4]], array_slice($eventos, 0, 8)), 'Nada vence nos próximos 7 dias.');
    }
}
