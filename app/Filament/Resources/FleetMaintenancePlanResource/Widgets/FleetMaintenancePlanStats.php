<?php

namespace App\Filament\Resources\FleetMaintenancePlanResource\Widgets;

use App\Models\FleetMaintenancePlan;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FleetMaintenancePlanStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $planos = FleetMaintenancePlan::with('fleetVehicle')->get();

        $total = $planos->count();
        $vencidos = $planos->filter(fn ($plan) => $plan->isVencido())->count();
        $proximosDoVencimento = $planos->filter(fn ($plan) => $plan->isProximoVencimento())->count();
        $veiculosCobertos = $planos->pluck('fleet_vehicle_id')->unique()->count();

        return [
            Stat::make('Total de Planos', $total)
                ->description('Planos de manutenção preventiva')
                ->color('gray'),

            Stat::make('Vencidos', $vencidos)
                ->description('Passaram do km/data previstos')
                ->color($vencidos > 0 ? 'danger' : 'success'),

            Stat::make('Próximos do Vencimento', $proximosDoVencimento)
                ->description('7 dias ou 500km de antecedência')
                ->color($proximosDoVencimento > 0 ? 'warning' : 'success'),

            Stat::make('Veículos Cobertos', $veiculosCobertos)
                ->description('Com ao menos 1 plano ativo')
                ->color('info'),
        ];
    }
}
