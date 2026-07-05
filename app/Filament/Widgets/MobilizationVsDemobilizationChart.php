<?php

namespace App\Filament\Widgets;

use App\Models\EquipmentMovement;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class MobilizationVsDemobilizationChart extends ChartWidget
{
    protected static ?string $heading = 'Mobilização x Desmobilização';

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = ['md' => 2];

    protected function getData(): array
    {
        $results = EquipmentMovement::select(
            DB::raw("to_char(created_at, 'YYYY-MM') as month_key"),
            DB::raw("to_char(created_at, 'Mon/YY') as month_label"),
            DB::raw("count(*) filter (where type = '".EquipmentMovement::TYPE_MOBILIZACAO."') as mobilizacao_count"),
            DB::raw("count(*) filter (where type = '".EquipmentMovement::TYPE_DESMOBILIZACAO."') as desmobilizacao_count")
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
                    'label' => 'Mobilização',
                    'data' => $results->pluck('mobilizacao_count')->toArray() ?: [0],
                    'backgroundColor' => '#3987e5',
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Desmobilização',
                    'data' => $results->pluck('desmobilizacao_count')->toArray() ?: [0],
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
                'legend' => [
                    'display' => true,
                    'labels' => ['color' => '#94a3b8'],
                ],
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
