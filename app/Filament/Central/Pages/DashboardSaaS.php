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
    // Sem isso, herda '/' da classe base e colide com DashboardVisitantes
    // (tambem na raiz) -- a rota nomeada "dashboard-saa-s" nunca fica
    // registrada, e o item de menu quebra com RouteNotFoundException ao
    // montar a navegacao (500 em QUALQUER pagina do painel Central, nao so'
    // nesta). Mesmo padrao ja usado em DashboardCrm.
    protected static string $routePath = 'dashboard-saas';

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
