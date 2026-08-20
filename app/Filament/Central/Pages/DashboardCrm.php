<?php

namespace App\Filament\Central\Pages;

use App\Filament\Central\Resources\SalesLeadResource\Widgets\InteractionChannelChart;
use App\Filament\Central\Resources\SalesLeadResource\Widgets\InteractionChannelStats;
use App\Filament\Central\Resources\SalesLeadResource\Widgets\LeadsByStageChart;
use App\Filament\Central\Resources\SalesLeadResource\Widgets\SalesLeadListStats;
use App\Filament\Central\Widgets\LeadsBySegmentChart;
use App\Filament\Central\Widgets\LeadsBySourceChart;
use App\Filament\Central\Widgets\LeadsCreatedTrendChart;
use App\Filament\Central\Widgets\ProspectingMapWidget;
use App\Filament\Central\Widgets\SalesCrmStatsWidget;
use App\Filament\Central\Widgets\SalesLeadMapWidget;
use App\Filament\Central\Widgets\WonLostTrendChart;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Reune todos os graficos/cards do modulo comercial num so lugar --
 * antes SalesLeadListStats/InteractionChannelStats/InteractionChannelChart/
 * LeadsByStageChart ficavam duplicados em cima do grid de ListSalesLeads,
 * que agora e' so a tabela (pedido do usuario 2026-08-19: "separe o grid
 * dos graficos"). Fundo branco so' nesta pagina (view propria, ver
 * resources/views/filament/central/pages/dashboard-crm.blade.php) -- resto
 * da Central continua no tema escuro "Convertico", pedido explicito de nao
 * mexer no resto.
 */
class DashboardCrm extends BaseDashboard
{
    protected static string $routePath = 'dashboard-crm';

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Dashboard CRM';

    protected static ?string $title = 'Dashboard CRM';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = -1;

    protected static string $view = 'filament.central.pages.dashboard-crm';

    public function getWidgets(): array
    {
        return [
            SalesLeadListStats::class,
            SalesCrmStatsWidget::class,
            InteractionChannelStats::class,
            InteractionChannelChart::class,
            LeadsByStageChart::class,
            LeadsCreatedTrendChart::class,
            WonLostTrendChart::class,
            LeadsBySegmentChart::class,
            LeadsBySourceChart::class,
            SalesLeadMapWidget::class,
            ProspectingMapWidget::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 2;
    }
}
