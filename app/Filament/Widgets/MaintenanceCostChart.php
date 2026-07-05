<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class MaintenanceCostChart extends ChartWidget
{
    protected static ?string $heading = 'Custo de Manutenção por Mês';

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = ['md' => 2];

    protected function getData(): array
    {
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

        return [
            'datasets' => [
                [
                    'label' => 'Custo Total (R$)',
                    'data' => $results->pluck('total_cost')->map(fn ($v) => round((float) $v, 2))->toArray() ?: [0],
                    'backgroundColor' => '#3987e5',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => '#334155'],
                    'ticks' => ['color' => '#94a3b8'],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#94a3b8'],
                ],
            ],
        ];
    }
}
