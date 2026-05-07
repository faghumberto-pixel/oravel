<?php

namespace App\Filament\Central\Widgets;

use App\Models\Tenant;
use Filament\Widgets\ChartWidget;

class ChurnChart extends ChartWidget
{
    protected static ?string $heading = 'Taxa de Retenção';
    protected static ?int $sort = 3;
    
    // Trava a altura na mesma medida do gráfico de linha
    protected static ?string $maxHeight = '275px';

    protected function getData(): array
    {
        $ativos = Tenant::whereIn('status', ['active', 'trial'])->count();
        $cancelados = Tenant::where('status', 'canceled')->count();

        if ($ativos == 0 && $cancelados == 0) { $ativos = 1; }

        return [
            'datasets' => [
                [
                    'label' => 'Base de Clientes',
                    'data' => [$ativos, $cancelados],
                    'backgroundColor' => ['#10b981', '#ef4444'],
                ],
            ],
            'labels' => ['Ativos', 'Churn'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}