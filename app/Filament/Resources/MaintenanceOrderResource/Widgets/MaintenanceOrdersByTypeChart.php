<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Widgets;

use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Filament\Widgets\ChartWidget;

/**
 * Mesmo padrão do PmpByEquipmentTypeChart (Dashboard PMP) -- aqui agrupado
 * por maintenance_type (Corretiva/Preventiva/Avaria/Troca/Emergência/...)
 * em vez de tipo de equipamento.
 */
class MaintenanceOrdersByTypeChart extends ChartWidget
{
    protected static ?string $heading = 'O.S. por Tipo de Manutenção';

    protected static ?string $maxHeight = '220px';

    protected int|string|array $columnSpan = ['md' => 1];

    protected function getData(): array
    {
        $counts = MaintenanceOrder::where('tenant_id', Tenancy::current()?->id)
            ->where('status', '!=', 'Cancelada')
            ->selectRaw("COALESCE(maintenance_type, 'Sem Tipo') as tipo, count(*) as total")
            ->groupBy('tipo')
            ->orderByDesc('total')
            ->pluck('total', 'tipo');

        return [
            'datasets' => [[
                'label' => 'OS',
                'data' => $counts->values()->all(),
                'backgroundColor' => '#3987e5',
                'borderRadius' => 5,
            ]],
            'labels' => $counts->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => '#334155'],
                    'ticks' => ['precision' => 0, 'color' => '#94a3b8'],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#94a3b8'],
                ],
            ],
        ];
    }
}
