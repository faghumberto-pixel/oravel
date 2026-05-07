<?php

namespace App\Filament\Central\Widgets;

use Filament\Widgets\ChartWidget;

class MrrChart extends ChartWidget
{
    protected static ?string $heading = 'Evolução do Faturamento';
    protected static ?int $sort = 2;
    
    // Trava a altura do gráfico para ficar alinhado
    protected static ?string $maxHeight = '275px';

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'MRR (R$)',
                    'data' => [1200, 2100, 3000, 4200, 5100, 6500],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}