<?php

namespace App\Filament\Resources\ClientResource\Widgets;

use App\Models\Client;
use App\Support\Tenancy;
use Filament\Widgets\ChartWidget;

class ClientsByNicheChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Clientes por Nicho';

    protected static ?string $maxHeight = '220px';

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getData(): array
    {
        $counts = Client::query()
            ->selectRaw('COALESCE(activity_type, \'nao_informado\') as nicho, count(*) as total')
            ->groupBy('nicho')
            ->pluck('total', 'nicho');

        $labels = Client::nicheLabels();
        $labels['nao_informado'] = 'Não Informado';

        return [
            'datasets' => [[
                'data' => $counts->values()->all(),
                'backgroundColor' => ['#3987e5', '#199e70', '#c98500', '#e6534d', '#8b5cf6', '#64748b'],
                'borderWidth' => 2,
                'borderColor' => '#0d1321',
            ]],
            'labels' => $counts->keys()->map(fn ($key) => $labels[$key] ?? 'Não Informado')->all(),
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
