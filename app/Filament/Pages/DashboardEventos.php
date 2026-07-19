<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CrmAgendaWidget;
use App\Filament\Widgets\CrmLeadMapWidget;
use App\Filament\Widgets\DamagesBySeverityChart;
use App\Filament\Widgets\EquipmentMovementRouteMapWidget;
use App\Filament\Widgets\LogisticaAgendaWidget;
use App\Filament\Widgets\MobilizationVsDemobilizationChart;
use App\Filament\Widgets\RadarOperacional;
use App\Filament\Widgets\ReplacementVsRepairChart;
use App\Filament\Widgets\ScheduledDispatchesWidget;
use App\Filament\Widgets\TopClientsByRentals;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;

/**
 * Widgets escolhidos pra bater com o ciclo curto e alta rotatividade de
 * locacao de evento (mobiliza/desmobiliza o tempo todo, funil de vendas
 * ativo, dano/reposicao checado a cada retorno) -- diferente de Construcao
 * Civil (equipamento fica meses no mesmo lugar) ou Industrial/Hospitalar
 * (foco em SLA de uptime). Ver App\Models\Tenant::applySegmentPreset()
 * pro mesmo raciocinio aplicado aos modulos habilitados.
 */
class DashboardEventos extends Dashboard
{
    protected static string $routePath = 'dashboard-eventos';

    protected static bool $shouldRegisterNavigation = false;

    public static function widgetList(): array
    {
        return [
            AccountWidget::class,
            RadarOperacional::class,
            LogisticaAgendaWidget::class,
            MobilizationVsDemobilizationChart::class,
            ScheduledDispatchesWidget::class,
            EquipmentMovementRouteMapWidget::class,
            CrmAgendaWidget::class,
            CrmLeadMapWidget::class,
            TopClientsByRentals::class,
            DamagesBySeverityChart::class,
            ReplacementVsRepairChart::class,
        ];
    }

    public function getWidgets(): array
    {
        return static::widgetList();
    }
}
