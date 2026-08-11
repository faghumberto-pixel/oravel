<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Charts\AreaChart;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Carbon;

/**
 * Ponte entre o AreaChart genérico (sem query interna, por design) e
 * App\Support\SegmentDashboardWidgets, que só registra widgets por
 * class-string (@livewire($widget, [], $widget), sem props). Esta classe é
 * quem faz a query real e repassa pro genérico via parent::mount().
 *
 * Diferente do Dashboard PMP (só Preventivas), aqui é a frota inteira --
 * métrica nova, não duplica nenhum gráfico já existente neste dashboard.
 *
 * Assinatura do mount() repete a de AreaChart::mount() de propósito
 * (parâmetros nunca usados) -- ver comentário equivalente em
 * FleetAvailabilityGaugeWidget::mount().
 */
class MaintenanceOrdersOpenVsClosedAreaWidget extends AreaChart
{
    private const MESES_ABREV = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    public function mount(
        array $labels = [],
        array $seriesA = [],
        array $seriesB = [],
        ?string $chartTitle = null,
        ?string $sourceNote = null,
        array $seriesC = [],
        bool $empilhar = false,
    ): void {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());
        $labels = $months->map(fn (Carbon $m) => self::MESES_ABREV[$m->month])->all();

        $abertas = $months->map(fn (Carbon $month) => MaintenanceOrder::whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->count())->all();

        $concluidas = $months->map(fn (Carbon $month) => MaintenanceOrder::whereNotNull('finished_at')
            ->whereBetween('finished_at', [$month, $month->copy()->endOfMonth()])
            ->count())->all();

        parent::mount(
            labels: $labels,
            seriesA: ['name' => 'Abertas', 'color' => '#c98500', 'data' => $abertas],
            seriesB: ['name' => 'Concluídas', 'color' => '#199e70', 'data' => $concluidas],
            chartTitle: 'O.S. Abertas vs. Concluídas por Mês',
        );
    }
}
