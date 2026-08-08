<?php

namespace App\Filament\Central\Widgets;

use App\Models\SiteVisit;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * "Por onde chegaram" -- agrupa por utm_source, com acessos sem UTM
 * contados como "Direto/Orgânico" em vez de somem do grafico.
 */
class AcquisitionChannelChart extends ChartWidget
{
    protected static ?string $heading = 'Canal de Aquisição (30 dias)';

    protected static ?int $sort = 4;

    protected static ?string $maxHeight = '275px';

    protected function getData(): array
    {
        $porCanal = SiteVisit::query()
            ->where('started_at', '>=', Carbon::now()->subDays(30))
            ->selectRaw("COALESCE(utm_source, 'Direto/Orgânico') as canal, COUNT(*) as total")
            ->groupBy('canal')
            ->orderByDesc('total')
            ->pluck('total', 'canal');

        return [
            'datasets' => [
                [
                    'data' => $porCanal->values()->all(),
                    'backgroundColor' => ['#8b5cf6', '#3b82f6', '#10b981', '#f59e0b', '#ec4899', '#06b6d4', '#64748b'],
                ],
            ],
            'labels' => $porCanal->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
