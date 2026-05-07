<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Facades\Filament;

class StatsOverview extends BaseWidget
{
    // Força o widget de estatísticas a ocupar a linha inteira no topo
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        // O jeito correto e seguro de pegar o Tenant no Filament Multi-tenancy
        $tenant = Filament::getTenant();

        // Segurança: Se não houver empresa selecionada, não renderiza os gráficos para evitar erro 500
        if (!$tenant) {
            return [];
        }

        $tenantId = $tenant->id;
        
        return [
            Stat::make('Total de Ativos', Asset::where('tenant_id', $tenantId)->count())
                ->description('Ativos cadastrados na frota')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),
                
            Stat::make('Em Manutenção', Asset::where('tenant_id', $tenantId)->where('status', 'manutencao')->count())
                ->description('Ativos parados')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('danger'),
                
            Stat::make('Disponibilidade', '91.8%') // Dica: Futuramente podemos calcular isso pelo histórico de OS
                ->description('Eficiência da frota')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('success'),
        ];
    }
}