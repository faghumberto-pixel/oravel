<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Widgets;

use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Filament\Widgets\ChartWidget;

/**
 * Mesmo padrão do PmpStatusDonutChart (Dashboard PMP), aqui sobre TODAS as
 * O.S. -- reagrupa internal_status (mesmo campo do Kanban de Oficina) em
 * Concluído/Em Andamento/Pendente/Bloqueado.
 */
class MaintenanceOrdersStatusDonutChart extends ChartWidget
{
    protected static ?string $heading = 'Status das O.S. Atuais';

    protected static ?string $maxHeight = '220px';

    protected int|string|array $columnSpan = ['md' => 1];

    protected function getData(): array
    {
        $orders = MaintenanceOrder::where('tenant_id', Tenancy::current()?->id)
            ->where('status', '!=', 'Cancelada')
            ->get(['internal_status']);

        $concluido = $orders->where('internal_status', 'concluido')->count();
        $andamento = $orders->where('internal_status', 'em_manutencao')->count();
        $bloqueado = $orders->whereIn('internal_status', ['aguardando_peca', 'aguardando_peca_canibalizado'])->count();
        $pendente = $orders->count() - $concluido - $andamento - $bloqueado;

        return [
            'datasets' => [[
                'data' => [$concluido, $andamento, $pendente, $bloqueado],
                'backgroundColor' => ['#199e70', '#3987e5', '#c98500', '#e6534d'],
                'borderWidth' => 0,
            ]],
            'labels' => ['Concluído', 'Em Andamento', 'Pendente', 'Bloqueado'],
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
                'legend' => ['display' => true, 'position' => 'bottom', 'labels' => ['color' => '#94a3b8', 'boxWidth' => 10]],
            ],
        ];
    }
}
