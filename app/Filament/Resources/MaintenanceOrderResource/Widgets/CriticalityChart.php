<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Widgets;

use App\Models\Asset;
use App\Models\CriticalityLevel;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class CriticalityChart extends ChartWidget
{
    protected static ?string $heading = 'Perfil de Risco da Frota';
    protected static ?string $maxHeight = '250px';

    protected function getData(): array
    {
        $tenantId = Auth::user()->tenant_id;
        // Busca todos os níveis cadastrados para garantir que o D não seja ignorado
        $levels = CriticalityLevel::where('tenant_id', $tenantId)->orderBy('code', 'asc')->get();
        
        $data = [];
        $labels = [];

        foreach ($levels as $level) {
            $labels[] = "Nível " . $level->code;
            // Contagem absoluta por ID de criticidade amarrada ao tenant
            $data[] = Asset::where('tenant_id', $tenantId)
                          ->where('criticality_level_id', $level->id)
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
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'y' => [
                    'grid' => ['color' => '#334155'], 
                    'ticks' => ['precision' => 0, 'color' => '#94a3b8']
                ],
                'x' => ['ticks' => ['color' => '#94a3b8']],
            ],
        ];
    }
}
