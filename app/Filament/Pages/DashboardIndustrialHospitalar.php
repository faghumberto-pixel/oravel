<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AgendaTecnicoWidget;
use App\Filament\Widgets\CompletedOrdersLast7DaysChart;
use App\Filament\Widgets\ListaAlertaAtivos;
use App\Filament\Widgets\MaintenanceByStatusChart;
use App\Filament\Widgets\MaintenanceCostChart;
use App\Filament\Widgets\RadarOperacional;
use App\Filament\Widgets\RadarUrgencia;
use App\Filament\Widgets\TechnicianOrderStats;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;

/**
 * Industrial/Hospitalar: equipamento critico (gerador de hospital etc),
 * o que importa e' SLA de uptime e atraso de manutencao, nao volume de
 * despacho/mobilizacao como Eventos nem custo acumulado como Construcao.
 * Ver App\Models\Tenant::applySegmentPreset() pro mesmo raciocinio.
 */
class DashboardIndustrialHospitalar extends Dashboard
{
    protected static string $routePath = 'dashboard-industrial-hospitalar';

    protected static bool $shouldRegisterNavigation = false;

    public static function widgetList(): array
    {
        return [
            AccountWidget::class,
            RadarUrgencia::class,
            RadarOperacional::class,
            MaintenanceByStatusChart::class,
            CompletedOrdersLast7DaysChart::class,
            ListaAlertaAtivos::class,
            MaintenanceCostChart::class,
            AgendaTecnicoWidget::class,
            TechnicianOrderStats::class,
        ];
    }

    public function getWidgets(): array
    {
        return static::widgetList();
    }
}
