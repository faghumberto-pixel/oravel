<?php

namespace App\Filament\Widgets\Charts;

use Filament\Widgets\ChartWidget;

/**
 * Duas séries preenchidas (área), sem empilhamento -- podem se cruzar
 * visualmente. Uso: métricas complementares/opostas ao longo do tempo
 * (ex.: Realizado vs. Planejado, Receita vs. Custo).
 *
 * Sem lógica de query aqui -- $labels/$seriesA/$seriesB já vêm prontos de
 * quem instancia o widget.
 */
class AreaChart extends ChartWidget
{
    protected static ?string $maxHeight = '220px';

    /** @var array<int, string> */
    public array $labels = [];

    /** @var array{name: string, color: string, data: array<int, int|float>} */
    public array $seriesA = [];

    /** @var array{name: string, color: string, data: array<int, int|float>} */
    public array $seriesB = [];

    public ?string $chartTitle = null;

    public ?string $sourceNote = null;

    /**
     * ChartWidget::mount() do Filament não recebe parâmetros -- ver
     * comentário equivalente em GaugeChart::mount() (PHP não deixa
     * subclasse exigir parâmetro obrigatório a mais que o pai). $labels/
     * $seriesA/$seriesB são conceitualmente obrigatórios mesmo com default.
     *
     * @param  array<int, string>  $labels
     * @param  array{name: string, color: string, data: array<int, int|float>}  $seriesA
     * @param  array{name: string, color: string, data: array<int, int|float>}  $seriesB
     */
    public function mount(
        array $labels = [],
        array $seriesA = [],
        array $seriesB = [],
        ?string $chartTitle = null,
        ?string $sourceNote = null,
    ): void {
        $this->labels = $labels;
        $this->seriesA = $seriesA;
        $this->seriesB = $seriesB;
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
        return 'line';
    }

    protected function getData(): array
    {
        return [
            'datasets' => [
                $this->seriesDataset($this->seriesA),
                $this->seriesDataset($this->seriesB),
            ],
            'labels' => $this->labels,
        ];
    }

    private function seriesDataset(array $series): array
    {
        $color = $series['color'] ?? '#3987e5';

        return [
            'label' => $series['name'] ?? '',
            'data' => $series['data'] ?? [],
            'borderColor' => $color,
            'backgroundColor' => $this->withAlpha($color, 0.18),
            'fill' => true,
            'tension' => 0.35,
            'pointRadius' => 0,
        ];
    }

    private function withAlpha(string $hex, float $alpha): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            return $hex;
        }

        [$r, $g, $b] = [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];

        return "rgba({$r},{$g},{$b},{$alpha})";
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                    'align' => 'end',
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
