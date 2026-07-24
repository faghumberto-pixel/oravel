<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Widgets;

use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Mesmo padrão do PmpEvolutionChart (Dashboard PMP), aqui sobre TODAS as
 * O.S. (não só preventivas) -- "Abertas" = criadas no mês, "Concluídas" =
 * finalizadas no mês (finished_at).
 */
class MaintenanceOrdersEvolutionChart extends ChartWidget
{
    protected static ?string $heading = 'Evolução Mensal de O.S. (Abertas vs. Concluídas)';

    protected static ?string $maxHeight = '220px';

    protected int|string|array $columnSpan = ['md' => 2];

    private const MESES_ABREV = [1 => 'Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

    protected function getData(): array
    {
        $tenantId = Tenancy::current()?->id;

        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i)->startOfMonth());

        $labels = $months->map(fn (Carbon $m) => self::MESES_ABREV[$m->month])->all();

        $abertas = $months->map(fn (Carbon $month) => MaintenanceOrder::where('tenant_id', $tenantId)
            ->where('status', '!=', 'Cancelada')
            ->whereBetween('created_at', [$month, $month->copy()->endOfMonth()])
            ->count())->all();

        $concluidas = $months->map(fn (Carbon $month) => MaintenanceOrder::where('tenant_id', $tenantId)
            ->whereIn('status', ['Concluída', 'Completado'])
            ->whereBetween('finished_at', [$month, $month->copy()->endOfMonth()])
            ->count())->all();

        return [
            'datasets' => [
                [
                    'label' => 'Abertas',
                    'data' => $abertas,
                    'borderColor' => '#c98500',
                    'backgroundColor' => 'rgba(201,133,0,.12)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
                [
                    'label' => 'Concluídas',
                    'data' => $concluidas,
                    'borderColor' => '#199e70',
                    'backgroundColor' => 'rgba(25,158,112,.15)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true, 'labels' => ['color' => '#94a3b8']],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => '#334155'],
                    'ticks' => ['precision' => 0, 'color' => '#94a3b8'],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#94a3b8'],
                ],
            ],
        ];
    }
}
