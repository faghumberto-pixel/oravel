<?php

namespace App\Filament\Resources\FreightCarrierResource\Widgets;

use App\Models\FreightCarrier;
use App\Models\FreightRecord;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FreightCarrierStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = FreightCarrier::count();

        $comFreteNoMes = FreightCarrier::whereHas('freightRecords', fn ($q) => $q->where('data', '>=', now()->startOfMonth()))->count();

        $fretesNoMes = FreightRecord::where('tipo', 'terceirizado')
            ->where('data', '>=', now()->startOfMonth())
            ->count();

        $custoNoMes = FreightRecord::where('tipo', 'terceirizado')
            ->where('data', '>=', now()->startOfMonth())
            ->sum('valor');

        return [
            Stat::make('Total de Transportadoras', $total)
                ->description('Cadastradas')
                ->color('gray'),

            Stat::make('Ativas no Mês', $comFreteNoMes)
                ->description('Com frete realizado')
                ->color('success'),

            Stat::make('Fretes Terceirizados no Mês', $fretesNoMes)
                ->description(now()->translatedFormat('F/Y'))
                ->color('info'),

            Stat::make('Custo Pago no Mês', 'R$ '.number_format((float) $custoNoMes, 2, ',', '.'))
                ->description('Fretes terceirizados')
                ->color('warning'),
        ];
    }
}
