<?php

namespace App\Filament\Widgets\Charts;

use Filament\Widgets\ChartWidget;

/**
 * Combo de barras (contagem, eixo Y esquerdo) + linha (percentual, eixo Y
 * direito) -- Chart.js suporta "mixed chart" declarando `type` por
 * dataset (sobrescrevendo o tipo base do chart) e um segundo eixo via
 * `yAxisID`. Uso principal: "Planejado" (barra) vs. "% Realizado" (linha)
 * -- duas grandezas de escala diferente (contagem absoluta x percentual)
 * que uma única série de área/linha simples deixa dificil de comparar
 * quando os valores absolutos são parecidos.
 *
 * Sem lógica de query aqui -- $labels/$barSeries/$lineSeries já vêm
 * prontos de quem instancia o widget (mesmo padrão de mount() sobrescrito
 * com a MESMA assinatura em toda subclasse, ver GaugeChart::mount()).
 */
class BarLineComboChart extends ChartWidget
{
    protected static ?string $maxHeight = '220px';

    /** @var array<int, string> */
    public array $labels = [];

    /** @var array{name: string, color: string, data: array<int, int|float>} */
    public array $barSeries = [];

    /** @var array{name: string, color: string, data: array<int, int|float>} */
    public array $lineSeries = [];

    public ?string $chartTitle = null;

    public ?string $sourceNote = null;

    /**
     * @param  array<int, string>  $labels
     * @param  array{name: string, color: string, data: array<int, int|float>}  $barSeries
     * @param  array{name: string, color: string, data: array<int, int|float>}  $lineSeries
     */
    public function mount(
        array $labels = [],
        array $barSeries = [],
        array $lineSeries = [],
        ?string $chartTitle = null,
        ?string $sourceNote = null,
    ): void {
        $this->labels = $labels;
        $this->barSeries = $barSeries;
        $this->lineSeries = $lineSeries;
        $this->chartTitle = $chartTitle;
        $this->sourceNote = $sourceNote;
        $this->dataChecksum = $this->generateDataChecksum();
    }

    public function getHeading(): ?string
    {
        return $this->chartTitle;
    }

    public function getDescription(): ?string
    {
        return $this->sourceNote;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $barColor = $this->barSeries['color'] ?? '#3987e5';
        $lineColor = $this->lineSeries['color'] ?? '#199e70';

        return [
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => $this->barSeries['name'] ?? '',
                    'data' => $this->barSeries['data'] ?? [],
                    'backgroundColor' => $barColor,
                    'borderRadius' => 4,
                    'yAxisID' => 'y',
                    'order' => 2,
                ],
                [
                    'type' => 'line',
                    'label' => $this->lineSeries['name'] ?? '',
                    'data' => $this->lineSeries['data'] ?? [],
                    'borderColor' => $lineColor,
                    'backgroundColor' => $lineColor,
                    'pointBackgroundColor' => $lineColor,
                    'pointBorderColor' => '#111827',
                    'pointBorderWidth' => 1,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                    'tension' => 0.3,
                    'fill' => false,
                    'yAxisID' => 'y1',
                    'order' => 1,
                ],
            ],
            'labels' => $this->labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'labels' => ['color' => '#94a3b8', 'boxWidth' => 10, 'usePointStyle' => true],
                ],
            ],
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'position' => 'left',
                    'beginAtZero' => true,
                    'grid' => ['color' => '#374151'],
                    'ticks' => ['precision' => 0, 'color' => '#94a3b8'],
                ],
                'y1' => [
                    'type' => 'linear',
                    'position' => 'right',
                    'beginAtZero' => true,
                    'max' => 100,
                    'grid' => ['drawOnChartArea' => false],
                    'ticks' => ['color' => '#94a3b8', 'stepSize' => 25],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#94a3b8'],
                ],
            ],
        ];
    }
}
