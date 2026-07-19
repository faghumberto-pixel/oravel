<?php

namespace App\Filament\Central\Pages;

use App\Filament\Central\Widgets\SalesCrmStatsWidget;
use App\Filament\Central\Widgets\SalesLeadMapWidget;
use Filament\Pages\Dashboard as BaseDashboard;

class DashboardCrm extends BaseDashboard
{
    protected static string $routePath = 'dashboard-crm';

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $navigationLabel = 'Dashboard CRM';

    protected static ?string $title = 'Dashboard CRM';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?int $navigationSort = -1;

    public function getWidgets(): array
    {
        return [
            SalesCrmStatsWidget::class,
            SalesLeadMapWidget::class,
        ];
    }
}
