<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Filament\Widgets\ChartWidget;

/**
 * Agrupa por ChecklistGroup do Ativo (o "tipo de equipamento" real já usado
 * pelos templates de plano de manutenção -- ver MaintenancePlan::applicableFor()),
 * não uma categoria nova.
 */
class PmpByEquipmentTypeChart extends ChartWidget
{
    protected static ?string $heading = 'O.S. por Tipo de Equipamento';

    protected static ?string $maxHeight = '220px';

    protected int|string|array $columnSpan = ['md' => 1];

    protected function getData(): array
    {
        $counts = MaintenanceOrder::where('maintenance_orders.tenant_id', Tenancy::current()?->id)
            ->where('maintenance_type', MaintenanceOrder::TYPE_PREVENTIVE)
            ->where('maintenance_orders.status', '!=', 'Cancelada')
            ->join('assets', 'assets.id', '=', 'maintenance_orders.asset_id')
            ->leftJoin('checklist_groups', 'checklist_groups.id', '=', 'assets.checklist_group_id')
            ->selectRaw("COALESCE(checklist_groups.name, 'Sem Grupo') as grupo, count(*) as total")
            ->groupBy('grupo')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'grupo');

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
