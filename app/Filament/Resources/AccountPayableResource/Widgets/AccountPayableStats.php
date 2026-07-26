<?php

namespace App\Filament\Resources\AccountPayableResource\Widgets;

use App\Models\AccountPayable;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccountPayableStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = AccountPayable::count();

        $valorEmAberto = AccountPayable::whereIn('status', ['pendente', 'atrasado'])->sum('amount');

        $pendentes = AccountPayable::where('status', 'pendente')->count();

        $atrasadas = AccountPayable::where('status', 'atrasado')->count();

        return [
            Stat::make('Total de Contas', $total)
                ->description('Todas cadastradas')
                ->color('gray'),

            Stat::make('Valor em Aberto', 'R$ '.number_format((float) $valorEmAberto, 2, ',', '.'))
                ->description('Pendentes + atrasadas')
                ->color('info'),

            Stat::make('Pendentes', $pendentes)
                ->description('Aguardando pagamento')
                ->color('warning'),

            Stat::make('Atrasadas', $atrasadas)
                ->description('Vencidas sem pagamento')
                ->color($atrasadas > 0 ? 'danger' : 'success'),
        ];
    }
}
