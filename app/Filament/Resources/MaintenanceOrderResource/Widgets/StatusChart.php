<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Widgets;

use App\Models\MaintenanceOrder;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class StatusChart extends ChartWidget
{
    protected static ?string $heading = 'Volume por Status de OS';
    protected static ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $tenantId = Auth::user()->tenant_id;
        $statuses = ['Aberto', 'Em Andamento', 'Pendente', 'Reprogramado', 'Concluída'];
        
        $counts = [];
        foreach ($statuses as $status) {
            $counts[] = MaintenanceOrder::where('tenant_id', $tenantId)
                                      ->where('status', $status)
                                      ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total',
                    'data' => $counts,
                    'backgroundColor' => [
                        '#6366f1', // Azul Neon
                        '#ec4899', // Rosa Neon
                        '#6366f1', 
                        '#ec4899',
                        '#6366f1', 
                    ],
                    'borderRadius' => 20, // Efeito cilíndrico
                    'barThickness' => 30,
                ],
            ],
            'labels' => $statuses,
        ];
    }

    protected function getType(): string { return 'bar'; }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => [
                    'grid' => ['color' => '#334155'], 
                    'ticks' => ['precision' => 0, 'color' => '#94a3b8']
                ],
                'x' => [
                    'grid' => ['display' => false], 
                    'ticks' => ['color' => '#94a3b8']
                ],
            ],
        ];
    }
}
