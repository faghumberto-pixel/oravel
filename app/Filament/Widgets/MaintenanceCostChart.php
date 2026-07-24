<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Charts\LineChartWithMarkers;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Facades\DB;

/**
 * Ponte entre o LineChartWithMarkers genérico (sem query interna, por
 * design) e App\Support\SegmentDashboardWidgets, que só registra widgets
 * por class-string (@livewire($widget, [], $widget), sem props). Antes era
 * um ChartWidget de barras próprio; a query não mudou, só o tipo visual.
 *
 * Assinatura do mount() repete a de LineChartWithMarkers::mount() de
 * propósito (parâmetros nunca usados) -- ver comentário equivalente em
 * FleetAvailabilityGaugeWidget::mount().
 */
class MaintenanceCostChart extends LineChartWithMarkers
{
    public function mount(
        array $labels = [],
        array $series = [],
        ?string $chartTitle = null,
        ?string $sourceNote = null,
        string $markerStyle = 'circle',
    ): void {
        $results = MaintenanceOrder::select(
            DB::raw("to_char(created_at, 'YYYY-MM') as month_key"),
            DB::raw("to_char(created_at, 'Mon/YY') as month_label"),
            DB::raw('sum(total_order_cost) as total_cost')
        )
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('month_key', 'month_label')
            ->orderBy('month_key')
            ->get();

        $labels = $results->pluck('month_label')->toArray();
        if (empty($labels)) {
            $labels = [now()->format('M/y')];
        }

        parent::mount(
            labels: $labels,
            series: [
                ['name' => 'Custo Total (R$)', 'color' => '#3987e5', 'data' => $results->pluck('total_cost')->map(fn ($v) => round((float) $v, 2))->toArray() ?: [0]],
            ],
            chartTitle: 'Custo de Manutenção por Mês',
        );
    }
}
