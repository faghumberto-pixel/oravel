<?php

namespace App\Filament\Widgets\Charts;

use Filament\Widgets\ChartWidget;

/**
 * Mostrador semicircular (0-100%) reutilizável: ponteiro + faixas de cor +
 * marcador de meta opcional, desenhados por cima de um doughnut de 180°
 * (a parte visual do ponteiro/meta/escala é uma plugin Chart.js registrada
 * globalmente, ver resources/views/filament/oravel-gauge-chart-plugin.blade.php).
 *
 * Sem lógica de query aqui -- quem instancia via @livewire(GaugeChart::class,
 * ['value' => ..., ...]) já traz o número pronto.
 */
class GaugeChart extends ChartWidget
{
    protected static ?string $maxHeight = '190px';

    public float $value = 0;

    public ?float $target = null;

    public ?string $chartTitle = null;

    /** @var array<int, array{to: float, color: string}> */
    public array $bands = [];

    public string $needleColor = '#e5e7eb';

    /**
     * ChartWidget::mount() do Filament não recebe parâmetros -- PHP não
     * permite que uma subclasse declare parâmetros OBRIGATÓRIOS a mais que
     * o método do pai (fatal error de LSP, confirmado via
     * `php artisan view:cache`). Por isso todos os parâmetros aqui têm
     * default, mesmo $value sendo conceitualmente obrigatório -- quem
     * instancia via @livewire(GaugeChart::class, [...]) sempre deve passá-lo.
     *
     * @param  array<int, array{to: float, color: string}>|null  $bands  Faixas cumulativas (ex.: até 50%, até 75%, até 100%). Default: vermelho/amarelo/verde.
     */
    public function mount(
        float $value = 0,
        ?float $target = null,
        ?string $chartTitle = null,
        ?array $bands = null,
        ?string $needleColor = null,
    ): void {
        $this->value = max(0, min(100, $value));
        $this->target = $target;
        $this->chartTitle = $chartTitle;
        $this->bands = $bands ?? [
            ['to' => 50, 'color' => '#e6534d'],
            ['to' => 75, 'color' => '#c98500'],
            ['to' => 100, 'color' => '#199e70'],
        ];
        $this->needleColor = $needleColor ?? '#e5e7eb';
        $this->dataChecksum = $this->generateDataChecksum();
    }

    public function getHeading(): ?string
    {
        return $this->chartTitle;
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $previous = 0;
        $sizes = [];
        $colors = [];
        $labels = [];

        foreach ($this->bands as $band) {
            $sizes[] = $band['to'] - $previous;
            $colors[] = $band['color'];
            $labels[] = $band['to'].'%';
            $previous = $band['to'];
        }

        return [
            'datasets' => [[
                'data' => $sizes,
                'backgroundColor' => $colors,
                'borderWidth' => 0,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): array
    {
        return [
            'circumference' => 180,
            'rotation' => -90,
            'cutout' => '72%',
            'layout' => [
                'padding' => ['top' => 8, 'bottom' => 0],
            ],
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => ['enabled' => false],
                'oravelGauge' => [
                    'value' => $this->value,
                    'target' => $this->target,
                    'needleColor' => $this->needleColor,
                ],
            ],
        ];
    }
}
