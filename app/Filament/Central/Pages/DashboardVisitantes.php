<?php

namespace App\Filament\Central\Pages;

use App\Filament\Central\Widgets\AcquisitionChannelChart;
use App\Filament\Central\Widgets\EngagementChart;
use App\Filament\Central\Widgets\SiteVisitsStatsOverview;
use App\Filament\Central\Widgets\TopReferrersChart;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Dashboard separado do DashboardSaaS de proposito -- este e' sobre
 * TRAFEGO/ACESSO (site_visits, TrackSiteVisit), aquele e' sobre metricas de
 * NEGOCIO (MRR/churn/tenants). Mesmo raciocinio de separacao ja documentado
 * em DashboardSaaS.
 */
class DashboardVisitantes extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-cursor-arrow-rays';

    protected static ?string $navigationLabel = 'Acessos e Visitantes';

    protected static ?string $title = 'Acessos e Visitantes';

    protected static ?int $navigationSort = -9;

    public function getWidgets(): array
    {
        return [
            SiteVisitsStatsOverview::class,
            EngagementChart::class,
            TopReferrersChart::class,
            AcquisitionChannelChart::class,
        ];
    }
}
