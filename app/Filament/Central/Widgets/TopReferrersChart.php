<?php

namespace App\Filament\Central\Widgets;

use App\Models\SiteVisit;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class TopReferrersChart extends ChartWidget
{
    protected static ?string $heading = 'Top Referências (30 dias)';

    protected static ?int $sort = 3;

    protected static ?string $maxHeight = '275px';

    protected function getData(): array
    {
        $top = SiteVisit::query()
            ->whereNotNull('referrer_host')
            ->where('started_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw('referrer_host, COUNT(*) as total')
            ->groupBy('referrer_host')
            ->orderByDesc('total')
            ->limit(10)
            ->pluck('total', 'referrer_host');

        return [
            'datasets' => [
                [
                    'label' => 'Acessos',
                    'data' => $top->values()->all(),
                    'backgroundColor' => '#3b82f6',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $top->keys()->all(),
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
        ];
    }
}
