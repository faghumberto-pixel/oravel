<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AssetUtilizationStats extends BaseWidget
{
    protected function getStats(): array
    {
        $tenantId = Auth::user()->tenant_id;

        // Ativos em campo vs Ativos parados
        $totalAssets = Asset::where('tenant_id', $tenantId)->count();
        $allocatedCount = Asset::where('tenant_id', $tenantId)->where('status', 'alocado')->count();
        $maintenanceCount = Asset::where('tenant_id', $tenantId)->where('status', 'manutencao')->count();

        // Cálculo de disponibilidade (simplificado)
        $availabilityRate = $totalAssets > 0 ? (($totalAssets - $maintenanceCount) / $totalAssets) * 100 : 0;

        return [
            Stat::make('Taxa de Disponibilidade', number_format($availabilityRate, 1) . '%')
                ->description('Ativos prontos para operação')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color($availabilityRate > 90 ? 'success' : 'warning'),

            Stat::make('Ativos em Cliente', $allocatedCount)
                ->description('Gerando receita em campo')
                ->descriptionIcon('heroicon-m-truck')
                ->color('info'),

            Stat::make('Em Manutenção', $maintenanceCount)
                ->description('Ativos indisponíveis no momento')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color($maintenanceCount > 0 ? 'danger' : 'success'),
        ];
    }
}