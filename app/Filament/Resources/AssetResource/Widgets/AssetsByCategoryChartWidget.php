<?php

namespace App\Filament\Resources\AssetResource\Widgets;

use App\Models\Asset;
use App\Support\Tenancy;
use Filament\Widgets\ChartWidget;

class AssetsByCategoryChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Ativos por Categoria';

    protected static ?string $maxHeight = '220px';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getData(): array
    {
        $counts = Asset::query()
            ->join('asset_categories', 'asset_categories.id', '=', 'assets.asset_category_id')
            ->selectRaw('asset_categories.name as categoria, count(*) as total')
            ->groupBy('asset_categories.name')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'categoria');

        $semCategoria = Asset::whereNull('asset_category_id')->count();
        if ($semCategoria > 0) {
            $counts->put('Sem Categoria', $semCategoria);
        }

        return [
            'datasets' => [[
                'data' => $counts->values()->all(),
                'backgroundColor' => ['#3987e5', '#199e70', '#c98500', '#e6534d', '#8b5cf6', '#0891b2', '#f59e0b', '#64748b', '#94a3b8'],
                'borderWidth' => 2,
                'borderColor' => '#0d1321',
            ]],
            'labels' => $counts->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => ['color' => '#94a3b8', 'padding' => 12],
                ],
            ],
        ];
    }
}
