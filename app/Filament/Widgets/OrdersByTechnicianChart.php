<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OrdersByTechnicianChart extends ChartWidget
{
    protected static ?string $heading = 'OS Abertas por Técnico';

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = ['md' => 2];

    protected function getData(): array
    {
        $results = MaintenanceOrder::query()
            ->whereNotIn('status', ['Concluída', 'Cancelada'])
            ->whereNotNull('technician_id')
            ->join('users', 'users.id', '=', 'maintenance_orders.technician_id')
            ->select('users.name as technician_name', DB::raw('count(*) as total'))
            ->groupBy('users.name')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        $labels = $results->pluck('technician_name')->toArray();
        if (empty($labels)) {
            $labels = ['Sem dados'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'OS abertas',
                    'data' => $results->pluck('total')->toArray() ?: [0],
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
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => '#334155'],
                    'ticks' => ['precision' => 0, 'color' => '#94a3b8'],
                ],
                'y' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#94a3b8'],
                ],
            ],
        ];
    }
}
