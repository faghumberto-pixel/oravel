<?php

namespace App\Filament\Resources\FleetDriverResource\Widgets;

use App\Models\FleetDriver;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FleetDriverStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = FleetDriver::count();
        $ativos = FleetDriver::where('active', true)->count();

        $cnhVencida = FleetDriver::whereNotNull('cnh_expiry_date')
            ->where('cnh_expiry_date', '<', now())
            ->count();

        $cnhVencendo = FleetDriver::whereNotNull('cnh_expiry_date')
            ->whereBetween('cnh_expiry_date', [now(), now()->addDays(30)])
            ->count();

        return [
            Stat::make('Total de Motoristas', $total)
                ->description('Próprios e terceiros')
                ->color('gray'),

            Stat::make('Ativos', $ativos)
                ->description('Disponíveis para escala')
                ->color('success'),

            Stat::make('CNH Vencida', $cnhVencida)
                ->description('Precisa regularizar')
                ->color($cnhVencida > 0 ? 'danger' : 'success'),

            Stat::make('CNH Vencendo', $cnhVencendo)
                ->description('Próximos 30 dias')
                ->color($cnhVencendo > 0 ? 'warning' : 'success'),
        ];
    }
}
