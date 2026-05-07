<?php

namespace App\Filament\Central\Widgets;

use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SaaSStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $clientesAtivos = Tenant::whereIn('status', ['active', 'trial'])->count();
        $mrrTotal = Tenant::where('status', 'active')->sum('mrr_value');
        $novosEsteMes = Tenant::whereMonth('created_at', now()->month)->count();
        $churn = Tenant::where('status', 'canceled')->count();

        // Cálculo básico do LTV Estimado
        $ticketMedio = $clientesAtivos > 0 ? ($mrrTotal / $clientesAtivos) : 0;
        $tempoDeVidaEstimadoEmMeses = 12; // Projeção inicial (pode ser ajustada no futuro)
        $ltv = $ticketMedio * $tempoDeVidaEstimadoEmMeses;

        return [
            Stat::make('Receita Recorrente (MRR)', 'R$ ' . number_format($mrrTotal, 2, ',', '.'))
                ->description('Faturamento mensal')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
                
            Stat::make('LTV (Lifetime Value)', 'R$ ' . number_format($ltv, 2, ',', '.'))
                ->description('Valor gerado por cliente')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'), // Cor azul claro para destacar
                
            Stat::make('Clientes Ativos', $clientesAtivos)
                ->description($novosEsteMes . ' novos este mês')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),
                
            Stat::make('Cancelamentos (Churn)', $churn)
                ->description('Perdas registradas')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color($churn > 0 ? 'danger' : 'success'),
        ];
    }
}