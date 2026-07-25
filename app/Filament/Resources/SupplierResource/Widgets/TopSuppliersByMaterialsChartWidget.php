<?php

namespace App\Filament\Resources\SupplierResource\Widgets;

use App\Models\Supplier;
use App\Support\Tenancy;
use Filament\Widgets\ChartWidget;

class TopSuppliersByMaterialsChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Top Fornecedores por Materiais Vinculados';

    protected static ?string $maxHeight = '220px';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getData(): array
    {
        $counts = Supplier::withCount('materials')
            ->orderByDesc('materials_count')
            ->limit(5)
            ->get()
            ->pluck('materials_count', 'name');

        return [
            'datasets' => [[
                'label' => 'Materiais',
                'data' => $counts->values()->all(),
                'backgroundColor' => '#3987e5',
                'borderRadius' => 5,
            ]],
            'labels' => $counts->keys()->all(),
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
