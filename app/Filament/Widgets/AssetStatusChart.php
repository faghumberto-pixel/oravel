<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class AssetStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Status dos Ativos';
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $data = Asset::where('tenant_id', Auth::user()->tenant_id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Ativos',
                    'data' => array_values($data),
                    'backgroundColor' => ['#fbbf24', '#10b981', '#ef4444'],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}