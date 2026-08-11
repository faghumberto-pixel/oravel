<?php

namespace App\Filament\Widgets\Charts;

use Filament\Widgets\ChartWidget;

/**
 * Duas ou tres series preenchidas (area). Por padrao (empilhar=false) nao
 * empilha -- podem se cruzar visualmente, uso pra metricas complementares/
 * opostas (ex.: Realizado vs. Planejado, Receita vs. Custo). Com
 * empilhar=true, as series se somam visualmente (a altura total da pilha
 * = soma no ponto), uso pra composicao de um total ao longo do tempo (ex.:
 * Preventiva + Corretiva = total de OS do mes).
 *
 * Sem lógica de query aqui -- $labels/$seriesA/$seriesB/$seriesC já vêm
 * prontos de quem instancia o widget. $seriesC é opcional (default vazio)
 * -- widgets com só 2 séries continuam funcionando sem alteração.
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

    /** @var array{name: string, color: string, data: array<int, int|float>} */
    public array $seriesC = [];

    public bool $empilhar = false;

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
     * @param  array{name: string, color: string, data: array<int, int|float>}  $seriesC
     */
    public function mount(
        array $labels = [],
        array $seriesA = [],
        array $seriesB = [],
        ?string $chartTitle = null,
        ?string $sourceNote = null,
        array $seriesC = [],
        bool $empilhar = false,
    ): void {
        $this->labels = $labels;
        $this->seriesA = $seriesA;
        $this->seriesB = $seriesB;
        $this->seriesC = $seriesC;
        $this->empilhar = $empilhar;
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
        $series = array_filter([$this->seriesA, $this->seriesB, $this->seriesC], fn ($s) => ! empty($s));

        return [
            'datasets' => array_map(fn ($s) => $this->seriesDataset($s), array_values($series)),
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
            'backgroundColor' => $this->empilhar ? $this->withAlpha($color, 0.75) : $this->withAlpha($color, 0.18),
            'fill' => $this->empilhar ? 'origin' : true,
            'stack' => $this->empilhar ? 'total' : null,
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
                    'stacked' => $this->empilhar,
                    'beginAtZero' => true,
                    'grid' => ['color' => '#374151'],
                    'ticks' => ['precision' => 0, 'color' => '#94a3b8'],
                ],
                'x' => [
                    'stacked' => $this->empilhar,
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#94a3b8'],
                ],
            ],
        ];
    }
}
