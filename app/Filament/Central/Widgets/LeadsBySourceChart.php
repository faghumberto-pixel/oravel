<?php

namespace App\Filament\Central\Widgets;

use App\Models\SalesLead;
use Filament\Widgets\ChartWidget;

class LeadsBySourceChart extends ChartWidget
{
    protected static ?string $heading = 'Leads por Origem';

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $labels = SalesLead::sourceLabels();

        $counts = SalesLead::query()
            ->selectRaw('source, count(*) as total')
            ->groupBy('source')
            ->pluck('total', 'source');

        $rows = $counts->keys()->map(fn (?string $source) => $source
            ? ($labels[$source] ?? $source)
            : 'Sem origem')->all();

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => $counts->values()->all(),
                    'backgroundColor' => '#3b82f6',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $rows,
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
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}
