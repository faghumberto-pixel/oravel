<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceOrder;
use Filament\Widgets\ChartWidget;

class CompletedOrdersLast7DaysChart extends ChartWidget
{
    protected static ?string $heading = 'OS Concluídas nos Últimos 7 Dias';

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = ['md' => 2];

    protected function getData(): array
    {
        $counts = MaintenanceOrder::where('status', 'Concluída')
            ->whereNotNull('finished_at')
            ->where('finished_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw("to_char(finished_at, 'YYYY-MM-DD') as day, count(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('d/m');
            $data[] = (int) ($counts[$date->format('Y-m-d')] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Concluídas',
                    'data' => $data,
                    'backgroundColor' => '#199e70',
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
