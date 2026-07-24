<?php

namespace App\Filament\Widgets\Charts;

use Filament\Widgets\ChartWidget;

/**
 * Série(s) temporal(is) simples com marcador em cada ponto (losango/círculo).
 * Sem legenda quando há apenas 1 série. Uso: séries mensais simples
 * (Receita, Nº de Locações, Horas de Manutenção).
 *
 * Sem lógica de query aqui -- $labels/$series já vêm prontos de quem
 * instancia o widget.
 */
class LineChartWithMarkers extends ChartWidget
{
    protected static ?string $maxHeight = '220px';

    /** @var array<int, string> */
    public array $labels = [];

    /** @var array<int, array{name: string, color: string, data: array<int, int|float>}> */
    public array $series = [];

    public ?string $chartTitle = null;

    public ?string $sourceNote = null;

    public string $markerStyle = 'circle';

    /**
     * ChartWidget::mount() do Filament não recebe parâmetros -- ver
     * comentário equivalente em GaugeChart::mount() (PHP não deixa
     * subclasse exigir parâmetro obrigatório a mais que o pai). $labels/
     * $series são conceitualmente obrigatórios mesmo com default.
     *
     * @param  array<int, string>  $labels
     * @param  array<int, array{name: string, color: string, data: array<int, int|float>}>  $series  1 ou mais séries
     */
    public function mount(
        array $labels = [],
        array $series = [],
        ?string $chartTitle = null,
        ?string $sourceNote = null,
        string $markerStyle = 'circle',
    ): void {
        $this->labels = $labels;
        $this->series = $series;
        $this->chartTitle = $chartTitle;
        $this->sourceNote = $sourceNote;
        $this->markerStyle = $markerStyle;
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
        return 'line';
    }

    protected function getData(): array
    {
        return [
            'datasets' => array_map(fn (array $series) => [
                'label' => $series['name'] ?? '',
                'data' => $series['data'] ?? [],
                'borderColor' => $series['color'] ?? '#3987e5',
                'backgroundColor' => $series['color'] ?? '#3987e5',
                'fill' => false,
                'tension' => 0.3,
                'pointStyle' => $this->markerStyle === 'diamond' ? 'rectRot' : 'circle',
                'pointRadius' => 4,
                'pointHoverRadius' => 6,
                'pointBackgroundColor' => $series['color'] ?? '#3987e5',
                'pointBorderColor' => '#111827',
                'pointBorderWidth' => 1,
            ], $this->series),
            'labels' => $this->labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => count($this->series) > 1,
                    'position' => 'bottom',
                    'labels' => ['color' => '#94a3b8', 'boxWidth' => 10, 'usePointStyle' => true],
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => '#374151'],
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
