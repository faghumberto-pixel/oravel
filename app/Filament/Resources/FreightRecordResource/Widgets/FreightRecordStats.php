<?php

namespace App\Filament\Resources\FreightRecordResource\Widgets;

use App\Models\FreightRecord;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FreightRecordStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $query = FreightRecord::where('data', '>=', now()->startOfMonth());

        $totalNoMes = (clone $query)->count();
        $custoNoMes = (clone $query)->sum('valor');
        $custoMotoristaNoMes = (clone $query)->sum('custo_motorista');

        $ticketMedio = $totalNoMes > 0 ? $custoNoMes / $totalNoMes : 0;

        return [
            Stat::make('Fretes no Mês', $totalNoMes)
                ->description(now()->translatedFormat('F/Y'))
                ->color('gray'),

            Stat::make('Custo de Frete no Mês', 'R$ '.number_format((float) $custoNoMes, 2, ',', '.'))
                ->description('Frota própria + terceirizado')
                ->color('info'),

            Stat::make('Custo com Motoristas', 'R$ '.number_format((float) $custoMotoristaNoMes, 2, ',', '.'))
                ->description('Horas de motorista no mês')
                ->color('warning'),

            Stat::make('Ticket Médio', 'R$ '.number_format((float) $ticketMedio, 2, ',', '.'))
                ->description('Por frete')
                ->color('success'),
        ];
    }
}
