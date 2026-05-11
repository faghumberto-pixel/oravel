<?php

namespace App\Filament\Central\Widgets;

use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SaaSStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        // 1. Soma real do MRR de todos os clientes no banco
        $mrrTotal = Tenant::sum('mrr_value');
        
        // 2. Contagem real de empresas
        $totalEmpresas = Tenant::count();

        return [
            Stat::make('Faturamento Mensal (MRR)', 'R$ ' . number_format($mrrTotal, 2, ',', '.'))
                ->description('Receita real acumulada no Oravel')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Empresas Ativas', $totalEmpresas)
                ->description('Total de clientes na plataforma')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('primary'),
                
            Stat::make('Ticket Médio', 'R$ ' . number_format($totalEmpresas > 0 ? $mrrTotal / $totalEmpresas : 0, 2, ',', '.'))
                ->description('Valor médio por contrato')
                ->color('info'),
        ];
    }
}