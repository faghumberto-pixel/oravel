<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;

class MaintenanceTrendsChart extends ChartWidget
{
    protected static ?string $heading = 'Evolução de Custos de Manutenção';
    
    // Ocupa 2 colunas (vai ficar à direita do gráfico Donut)
    protected int | string | array $columnSpan = 2;
    
    // Limita a altura para alinhar perfeitamente com o gráfico ao lado
    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        return [
            'datasets' => [
                [
                    'label' => 'Custo Real',
                    'data' => [4500, 5200, 4800, 7000, 6100, 5900],
                    'borderColor' => '#ef4444',
                    'fill' => 'start',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                ],
                [
                    'label' => 'Orçado',
                    'data' => [5000, 5000, 5000, 5000, 5000, 5000],
                    'borderColor' => '#374151',
                    'borderDash' => [5, 5],
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