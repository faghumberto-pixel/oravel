<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AgendaCampo;
use App\Filament\Widgets\AgendaTecnicoWidget;
use App\Filament\Widgets\AssetsByStatusChart;
use App\Filament\Widgets\ListaAlertaAtivos;
use App\Filament\Widgets\ListaAtivosParados;
use App\Filament\Widgets\MaintenanceByStatusChart;
use App\Filament\Widgets\MaintenanceCostChart;
use App\Filament\Widgets\RadarOperacional;
use App\Filament\Widgets\TechnicianOrderStats;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;

/**
 * Construcao civil: equipamento pesado parado num canteiro por meses, o
 * que importa e' saber o que ta parado/em manutencao e o custo acumulado,
 * nao o vai-e-volta constante de Eventos. Ver
 * App\Models\Tenant::applySegmentPreset() pro mesmo raciocinio.
 */
class DashboardConstrucaoCivil extends Dashboard
{
    protected static string $routePath = 'dashboard-construcao-civil';

    protected static bool $shouldRegisterNavigation = false;

    public static function widgetList(): array
    {
        return [
            AccountWidget::class,
            RadarOperacional::class,
            AssetsByStatusChart::class,
            ListaAtivosParados::class,
            ListaAlertaAtivos::class,
            MaintenanceByStatusChart::class,
            MaintenanceCostChart::class,
            AgendaCampo::class,
            AgendaTecnicoWidget::class,
            TechnicianOrderStats::class,
        ];
    }

    public function getWidgets(): array
    {
        return static::widgetList();
    }
}
