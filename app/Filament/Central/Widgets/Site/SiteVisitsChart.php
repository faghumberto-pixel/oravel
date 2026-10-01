<?php

namespace App\Filament\Central\Widgets\Site;

use App\Models\WebVisit;
use App\Support\WebAnalytics;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class SiteVisitsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected static ?string $heading = 'Visitas por dia';

    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $since = WebAnalytics::since($this->filters['period'] ?? '7');

        $rows = WebVisit::query()
            ->where('started_at', '>=', $since)
            ->selectRaw('date(started_at) as day, count(*) as visits, count(distinct visitor_token) as visitors')
            ->groupBy('day')
            ->pluck('visits', 'day')
            ->all();
        $visitors = WebVisit::query()
            ->where('started_at', '>=', $since)
            ->selectRaw('date(started_at) as day, count(distinct visitor_token) as visitors')
            ->groupBy('day')
            ->pluck('visitors', 'day')
            ->all();

        $labels = [];
        $visitsData = [];
        $visitorsData = [];
        for ($day = $since->copy(); $day->lte(now()); $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $day->format('d/m');
            $visitsData[] = (int) ($rows[$key] ?? 0);
            $visitorsData[] = (int) ($visitors[$key] ?? 0);
        }

        return [
            'datasets' => [
                ['label' => 'Visitas', 'data' => $visitsData, 'borderColor' => '#ea580c', 'backgroundColor' => 'rgba(234,88,12,.15)', 'fill' => true, 'tension' => 0.3],
                ['label' => 'Visitantes únicos', 'data' => $visitorsData, 'borderColor' => '#3b82f6', 'tension' => 0.3],
            ],
            'labels' => $labels,
        ];
    }
}
