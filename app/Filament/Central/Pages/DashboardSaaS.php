<?php

namespace App\Filament\Central\Pages;

use App\Filament\Central\Widgets\ArrChart;
use App\Filament\Central\Widgets\ChurnChart;
use App\Filament\Central\Widgets\RevenueChart;
use App\Filament\Central\Widgets\SaaSStatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Dashboard separado do CRM de propósito -- antes tudo (métricas de SaaS +
 * métricas comerciais) ficava misturado no Dashboard padrão do Filament.
 * MrrChart/EngagementChart existem mas ficam de fora aqui: os dois usam
 * dado 100% inventado (sem nenhuma query real, comentário explícito "dados
 * simulados" no EngagementChart) -- incluir eles mostraria número falso
 * como se fosse real métrica de faturamento/engajamento.
 */
class DashboardSaaS extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationLabel = 'Dashboard SaaS';

    protected static ?string $title = 'Dashboard SaaS';

    protected static ?int $navigationSort = -10;

    public function getWidgets(): array
    {
        return [
            SaaSStatsOverview::class,
            RevenueChart::class,
            ChurnChart::class,
            ArrChart::class,
        ];
    }
}
