<?php

namespace App\Filament\Central\Widgets;

use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SaaSStatsOverview extends BaseWidget
{
    // Define que este widget aparece primeiro
    protected static ?int $sort = 1;

    // Ocupa a largura total da linha superior
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $clientesAtivos = Tenant::whereIn('status', ['active', 'trial'])->count();
        $mrrTotal = Tenant::where('status', 'active')->sum('mrr_value');
        $novosEsteMes = Tenant::whereMonth('created_at', now()->month)->count();
        $churn = Tenant::where('status', 'canceled')->count();

        return [
            Stat::make('Receita Recorrente (MRR)', 'R$ ' . number_format($mrrTotal, 2, ',', '.'))
                ->description('Faturamento mensal do Oravel')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
                
            Stat::make('Clientes Ativos', $clientesAtivos)
                ->description($novosEsteMes . ' novos contratos este mês')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),
                
            Stat::make('Cancelamentos (Churn)', $churn)
                ->description('Total de perdas registradas')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color($churn > 0 ? 'danger' : 'success'),
        ];
    }
}