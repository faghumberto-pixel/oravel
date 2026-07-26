<?php

namespace App\Filament\Resources\AccountReceivableResource\Widgets;

use App\Models\AccountReceivable;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountReceivableStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = AccountReceivable::count();

        $valorAReceber = AccountReceivable::whereIn('status', ['pendente', 'atrasado'])->sum('amount');

        $pendentes = AccountReceivable::where('status', 'pendente')->count();

        $atrasadas = AccountReceivable::where('status', 'atrasado')->count();

        return [
            Stat::make('Total de Contas', $total)
                ->description('Todas cadastradas')
                ->color('gray'),

            Stat::make('Valor a Receber', 'R$ '.number_format((float) $valorAReceber, 2, ',', '.'))
                ->description('Pendentes + atrasadas')
                ->color('info'),

            Stat::make('Pendentes', $pendentes)
                ->description('Aguardando recebimento')
                ->color('warning'),

            Stat::make('Atrasadas', $atrasadas)
                ->description('Vencidas sem recebimento')
                ->color($atrasadas > 0 ? 'danger' : 'success'),
        ];
    }
}
