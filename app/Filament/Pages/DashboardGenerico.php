<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AssetsByStatusChart;
use App\Filament\Widgets\MaintenanceByStatusChart;
use App\Filament\Widgets\MaintenanceCostChart;
use App\Filament\Widgets\RadarOperacional;
use App\Filament\Widgets\RadarUrgencia;
use App\Filament\Widgets\TechnicianOrderStats;
use App\Filament\Widgets\TopClientsByRentals;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;

/**
 * Fallback pra tenant sem segmento definido (campo `segment` vazio) e
 * pro super admin sem tenant "atuando" selecionado -- mix generico
 * cobrindo os eixos principais (frota, manutencao, custo, urgencia)
 * sem nada exclusivo de Eventos/Construcao/Industrial.
 */
class DashboardGenerico extends Dashboard
{
    protected static string $routePath = 'dashboard-generico';

    protected static bool $shouldRegisterNavigation = false;

    public static function widgetList(): array
    {
        return [
            AccountWidget::class,
            RadarOperacional::class,
            AssetsByStatusChart::class,
            MaintenanceByStatusChart::class,
            MaintenanceCostChart::class,
            RadarUrgencia::class,
            TechnicianOrderStats::class,
            TopClientsByRentals::class,
        ];
    }

    public function getWidgets(): array
    {
        return static::widgetList();
    }
}
