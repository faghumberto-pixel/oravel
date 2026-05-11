<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Widgets;

use App\Models\Asset;
use App\Models\CriticalityLevel;
use Filament\Widgets\ChartWidget;
use Filament\Facades\Filament;

class CriticalityChart extends ChartWidget
{
    protected static ?string $heading = 'Perfil de Risco da Frota';
    protected static ?string $maxHeight = '250px';

    protected function getData(): array
    {
        // Pega o ID do Tenant atual de forma segura
        $tenantId = Filament::getTenant()->id;
        
        // Busca todos os níveis cadastrados para garantir que nenhum seja ignorado
        $levels = CriticalityLevel::where('tenant_id', $tenantId)
            ->orderBy('code', 'asc')
            ->get();
        
        $data = [];
        $labels = [];

        foreach ($levels as $level) {
            $labels[] = "Nível " . $level->code;
            
            /** * CORREÇÃO CRÍTICA:
             * Alterado de 'criticality_level_id' para 'criticality_level'
             * para bater com a coluna real do seu PostgreSQL.
             */
            $data[] = Asset::where('tenant_id', $tenantId)
                          ->where('criticality_level', $level->id)
                          ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ativos',
                    'data' => $data,
                    'fill' => 'start',
                    'borderColor' => '#ec4899',
                    'backgroundColor' => 'rgba(236, 72, 153, 0.1)',
                    'tension' => 0.4,
                    'pointRadius' => 6,
                    'pointBackgroundColor' => '#ec4899',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string { return 'line'; }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false]
            ],
            'scales' => [
                'y' => [
                    'grid' => ['color' => '#334155'], 
                    'ticks' => [
                        'precision' => 0, 
                        'color' => '#94a3b8'
                    ]
                ],
                'x' => [
                    'ticks' => ['color' => '#94a3b8']
                ],
            ],
        ];
    }
}