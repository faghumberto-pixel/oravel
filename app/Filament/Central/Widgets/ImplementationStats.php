<?php

namespace App\Filament\Central\Widgets;

use App\Models\ImplementationCharge;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Resumo da implantação: a receber, recebido e em atraso (todas as parcelas, todos os clientes). */
class ImplementationStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected function getStats(): array
    {
        $sum = fn (array $statuses) => (float) ImplementationCharge::withoutGlobalScopes()->whereIn('status', $statuses)->sum('amount');
        $count = fn (array $statuses) => ImplementationCharge::withoutGlobalScopes()->whereIn('status', $statuses)->count();
        $brl = fn (float $v) => 'R$ '.number_format($v, 2, ',', '.');

        return [
            Stat::make('A receber', $brl($sum([ImplementationCharge::PENDENTE, ImplementationCharge::ATRASADO])))
                ->description($count([ImplementationCharge::PENDENTE, ImplementationCharge::ATRASADO]).' parcela(s) em aberto')
                ->color('warning'),
            Stat::make('Recebido', $brl($sum([ImplementationCharge::PAGO])))
                ->description($count([ImplementationCharge::PAGO]).' parcela(s) paga(s)')
                ->color('success'),
            Stat::make('Em atraso', $brl($sum([ImplementationCharge::ATRASADO])))
                ->description($count([ImplementationCharge::ATRASADO]).' parcela(s) atrasada(s)')
                ->color('danger'),
        ];
    }
}
