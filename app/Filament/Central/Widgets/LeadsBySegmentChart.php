<?php

namespace App\Filament\Central\Widgets;

use App\Models\Client;
use App\Models\SalesLead;
use Filament\Widgets\ChartWidget;

class LeadsBySegmentChart extends ChartWidget
{
    protected static ?string $heading = 'Leads por Segmento';

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $counts = SalesLead::query()
            ->selectRaw('segment, count(*) as total')
            ->groupBy('segment')
            ->pluck('total', 'segment');

        $labels = $counts->keys()->map(fn (?string $segment) => $segment
            ? (Client::nicheLabels()[$segment] ?? $segment)
            : 'Sem segmento')->all();

        return [
            'datasets' => [
                [
                    'data' => $counts->values()->all(),
                    'backgroundColor' => ['#64748b', '#3b82f6', '#9333ea', '#f97316', '#059669', '#dc2626', '#ca8a04'],
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
