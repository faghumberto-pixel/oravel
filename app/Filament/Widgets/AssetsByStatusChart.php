<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use Filament\Widgets\ChartWidget;

class AssetsByStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Ativos por Status';

    protected static ?string $maxHeight = '280px';

    protected int|string|array $columnSpan = ['md' => 1];

    /**
     * Vocabulario real de Asset.status (AssetResource::form()) -- nada alem
     * disso e atribuido de verdade em nenhum lugar do sistema.
     */
    private const STATUSES = [
        'disponivel' => 'Disponível',
        'locado' => 'Locado',
        'operando' => 'Em Operação',
        'manutencao' => 'Em Manutenção',
    ];

    /**
     * Paleta categorica validada (dataviz skill, dark mode) -- ordem fixa,
     * nunca ciclada por significado.
     */
    private const COLORS = ['#3987e5', '#199e70', '#c98500', '#008300'];

    protected function getData(): array
    {
        $counts = [];
        foreach (self::STATUSES as $status => $label) {
            $counts[] = Asset::where('status', $status)->count();
        }

        return [
            'datasets' => [
                [
                    'data' => $counts,
                    'backgroundColor' => self::COLORS,
                    'borderWidth' => 2,
                    'borderColor' => '#0d1321',
                ],
            ],
            'labels' => array_values(self::STATUSES),
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
