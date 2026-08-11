<?php

namespace App\Filament\Widgets\GestaoAVista;

use App\Services\GestaoAVistaService;
use App\Support\Tenancy;
use Filament\Widgets\ChartWidget;

/**
 * Distribuição % de OS finalizadas por maintenance_type, mesmo padrão
 * visual de MaintenanceOrdersStatusDonutChart. O sistema não tem tipo
 * "Preditiva" -- mostra as categorias reais existentes (Preventiva/
 * Corretiva/etc), não um vocabulário fixo de 3 categorias genéricas.
 */
class TiposManutencaoDonutChart extends ChartWidget
{
    protected static ?string $heading = 'Tipos de Manutenção';

    protected static ?string $maxHeight = '220px';

    protected int|string|array $columnSpan = ['md' => 1];

    protected ?string $from = null;

    protected ?string $until = null;

    protected ?string $branchId = null;

    protected ?string $assetId = null;

    public function mount(
        ?string $from = null,
        ?string $until = null,
        ?string $branchId = null,
        ?string $assetId = null,
    ): void {
        $this->from = $from;
        $this->until = $until;
        $this->branchId = $branchId;
        $this->assetId = $assetId;
    }

    private const CORES = ['#3987e5', '#199e70', '#c98500', '#e6534d', '#8b5cf6', '#94a3b8'];

    protected function getData(): array
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return ['datasets' => [['data' => [], 'backgroundColor' => []]], 'labels' => []];
        }

        $service = new GestaoAVistaService($tenant->id);
        $distribuicao = $service->distribuicaoPorTipo([
            'from' => $this->from,
            'until' => $this->until,
            'branchId' => $this->branchId,
            'assetId' => $this->assetId,
        ]);

        return [
            'datasets' => [[
                'data' => array_column($distribuicao, 'quantidade'),
                'backgroundColor' => array_slice(self::CORES, 0, count($distribuicao)),
                'borderWidth' => 0,
            ]],
            'labels' => array_column($distribuicao, 'tipo'),
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
