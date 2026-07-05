<?php

namespace App\Filament\Resources\ContractResource\Widgets;

use App\Models\Contract;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ContractStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = Contract::count();
        $ativos = Contract::where('status', 'Ativo')->count();

        $venceEm30Dias = Contract::where('status', 'Ativo')
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [now(), now()->addDays(30)])
            ->count();

        $valorAtivos = Contract::where('status', 'Ativo')->sum('price');

        return [
            Stat::make('Total de Contratos', $total)
                ->description('Todos os status')
                ->color('gray'),

            Stat::make('Contratos Ativos', $ativos)
                ->description('Em vigência')
                ->color('success'),

            Stat::make('A Vencer em 30 dias', $venceEm30Dias)
                ->description('Ativos com fim de vigência próximo')
                ->color($venceEm30Dias > 0 ? 'warning' : 'success'),

            Stat::make('Valor em Contratos Ativos', 'R$ '.number_format((float) $valorAtivos, 2, ',', '.'))
                ->description('Receita mensal contratada')
                ->color('info'),
        ];
    }
}
