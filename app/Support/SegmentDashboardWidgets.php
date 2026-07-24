<?php

namespace App\Support;

use App\Filament\Widgets\AgendaCampo;
use App\Filament\Widgets\AgendaTecnicoWidget;
use App\Filament\Widgets\AssetsByStatusChart;
use App\Filament\Widgets\CompletedOrdersLast7DaysChart;
use App\Filament\Widgets\CrmAgendaWidget;
use App\Filament\Widgets\CrmLeadMapWidget;
use App\Filament\Widgets\DamagesBySeverityChart;
use App\Filament\Widgets\EquipmentMovementRouteMapWidget;
use App\Filament\Widgets\FleetAvailabilityGaugeWidget;
use App\Filament\Widgets\ListaAlertaAtivos;
use App\Filament\Widgets\ListaAtivosParados;
use App\Filament\Widgets\LogisticaAgendaWidget;
use App\Filament\Widgets\MaintenanceByStatusChart;
use App\Filament\Widgets\MaintenanceCostChart;
use App\Filament\Widgets\MaintenanceOrdersOpenVsClosedAreaWidget;
use App\Filament\Widgets\MobilizationVsDemobilizationChart;
use App\Filament\Widgets\RadarOperacional;
use App\Filament\Widgets\RadarUrgencia;
use App\Filament\Widgets\ReplacementVsRepairChart;
use App\Filament\Widgets\ScheduledDispatchesWidget;
use App\Filament\Widgets\TechnicianOrderStats;
use App\Filament\Widgets\TopClientsByRentals;
use App\Models\Client;

/**
 * Widgets exclusivos da aba "Painel de Gestao" (PainelGestao) por
 * tenants.segment -- pedido explicito: cada segmento (Eventos/Construcao
 * Civil/Industrial-Hospitalar) enxerga um conjunto proprio, nao um
 * dashboard generico com tudo misturado. Mesma logica de
 * App\Models\Tenant::applySegmentPreset() (modulos habilitados por
 * segmento), so que pra widgets em vez de features.
 */
class SegmentDashboardWidgets
{
    public static function forSegment(?string $segment): array
    {
        return match ($segment) {
            Client::NICHE_EVENTOS => [
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
            ],
            Client::NICHE_CONSTRUCAO_CIVIL => [
                RadarOperacional::class,
                AssetsByStatusChart::class,
                ListaAtivosParados::class,
                ListaAlertaAtivos::class,
                MaintenanceByStatusChart::class,
                MaintenanceCostChart::class,
                AgendaCampo::class,
                AgendaTecnicoWidget::class,
                TechnicianOrderStats::class,
            ],
            Client::NICHE_INDUSTRIAL_HOSPITALAR => [
                RadarUrgencia::class,
                RadarOperacional::class,
                MaintenanceByStatusChart::class,
                CompletedOrdersLast7DaysChart::class,
                ListaAlertaAtivos::class,
                MaintenanceCostChart::class,
                AgendaTecnicoWidget::class,
                TechnicianOrderStats::class,
            ],
            default => [
                RadarOperacional::class,
                AssetsByStatusChart::class,
                MaintenanceByStatusChart::class,
                MaintenanceCostChart::class,
                RadarUrgencia::class,
                TechnicianOrderStats::class,
                TopClientsByRentals::class,
                FleetAvailabilityGaugeWidget::class,
                MaintenanceOrdersOpenVsClosedAreaWidget::class,
            ],
        };
    }
}
