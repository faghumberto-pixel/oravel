<?php

namespace App\Filament\Resources\MaintenancePlanResource\Widgets;

use App\Models\MaintenancePlan;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class MaintenancePlanStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $planos = MaintenancePlan::with('asset')->get();

        $total = $planos->count();
        $ativos = $planos->where('is_active', true)->count();

        $vencidos = $planos->filter(function ($plan) {
            if (! $plan->is_active || ! $plan->asset) {
                return false;
            }

            return (float) $plan->asset->horimetro_atual >= ($plan->last_service_hours + $plan->interval_hours);
        })->count();

        $ativosCobertos = $planos->pluck('asset_id')->unique()->count();

        return [
            Stat::make('Total de Planos', $total)
                ->description('Manutenções preventivas')
                ->color('gray'),

            Stat::make('Ativos', $ativos)
                ->description('Em monitoramento')
                ->color('info'),

            Stat::make('Vencidos por Horímetro', $vencidos)
                ->description('Ultrapassaram o intervalo previsto')
                ->color($vencidos > 0 ? 'danger' : 'success'),

            Stat::make('Ativos Cobertos', $ativosCobertos)
                ->description('Com ao menos 1 plano')
                ->color('success'),
        ];
    }
}
