<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AssetUtilizationStats extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $tenantId = Auth::user()->tenant_id;

        return [
            Stat::make('Ativos em Operação', Asset::where('tenant_id', $tenantId)->where('status', 'alocado')->count())
                ->description('Ativos atualmente em contrato')
                ->descriptionIcon('heroicon-m-play')
                ->color('success'),

            Stat::make('Ativos em Manutenção', Asset::where('tenant_id', $tenantId)->where('status', 'manutencao')->count())
                ->description('Indisponíveis para locação')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('danger'),

            Stat::make('Disponibilidade da Frota', function() use ($tenantId) {
                $total = Asset::where('tenant_id', $tenantId)->count();
                if ($total === 0) return '0%';
                $disponiveis = Asset::where('tenant_id', $tenantId)->where('status', 'disponivel')->count();
                return number_format(($disponiveis / $total) * 100, 1) . '%';
            })
                ->description('Prontos para novos contratos')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('info'),
        ];
    }
}