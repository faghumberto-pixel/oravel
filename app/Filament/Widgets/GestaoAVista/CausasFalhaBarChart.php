<?php

namespace App\Filament\Widgets\GestaoAVista;

use App\Services\GestaoAVistaService;
use App\Support\Tenancy;
use Filament\Widgets\ChartWidget;

/**
 * Principais Causas de Falha (barra horizontal), mesmo padrão de
 * TopSuppliersByMaterialsChartWidget (indexAxis: 'y'). Sem plugin
 * chartjs-plugin-datalabels (não instalado no projeto, fora de escopo
 * introduzir dependência JS nova aqui) -- a % de cada categoria vai
 * embutida no próprio label do eixo Y ("Hidráulico (45%)"), não como
 * rótulo desenhado no fim da barra.
 */
class CausasFalhaBarChart extends ChartWidget
{
    protected static ?string $heading = 'Principais Causas de Falha';

    protected static ?string $maxHeight = '260px';

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

    protected function getData(): array
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return ['datasets' => [['data' => [], 'backgroundColor' => '#3987e5']], 'labels' => []];
        }

        $service = new GestaoAVistaService($tenant->id);
        $causas = $service->principaisCausasFalha([
            'from' => $this->from,
            'until' => $this->until,
            'branchId' => $this->branchId,
            'assetId' => $this->assetId,
        ]);

        return [
            'datasets' => [[
                'label' => '% das corretivas',
                'data' => array_column($causas, 'percentual'),
                'backgroundColor' => '#c98500',
                'borderRadius' => 5,
            ]],
            'labels' => array_map(
                fn (array $c) => "{$c['label']} ({$c['percentual']}%)",
                $causas
            ),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'max' => 100,
                    'grid' => ['color' => '#334155'],
                    'ticks' => ['precision' => 0, 'color' => '#94a3b8'],
                ],
                'y' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#94a3b8'],
                ],
            ],
        ];
    }
}
