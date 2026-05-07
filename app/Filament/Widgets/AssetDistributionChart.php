<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class AssetDistributionChart extends ChartWidget
{
    protected static ?string $heading = 'Faturamento vs. Custos (Visão Geral)';
    
    // Ocupa a linha toda
    protected int | string | array $columnSpan = 'full';
    
    // Limita a altura
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Faturamento',
                    'data' => [12000, 15000, 18000, 14000, 22000, 25000],
                    'backgroundColor' => '#f59e0b', // Âmbar
                ],
                [
                    'label' => 'Custos Operacionais',
                    'data' => [8000, 9000, 10000, 8500, 11000, 12000],
                    'backgroundColor' => '#374151', // Cinza
                ],
            ],
            'labels' => ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}