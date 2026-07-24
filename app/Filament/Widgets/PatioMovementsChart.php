<?php

namespace App\Filament\Widgets;

use App\Models\PatioEntry;
use App\Support\Tenancy;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Entradas vs Saídas na portaria, últimos 7 dias -- mesma ideia do gráfico
 * de evolução do Dashboard PMP (PmpEvolutionChart), duas linhas comparando
 * volumes reais por dia.
 */
class PatioMovementsChart extends ChartWidget
{
    protected static ?string $heading = 'Movimentações na Portaria (7 dias)';

    protected static ?string $maxHeight = '200px';

    protected int|string|array $columnSpan = ['md' => 2];

    private const DIAS_ABREV = [0 => 'Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    protected function getData(): array
    {
        $tenantId = Tenancy::current()?->id;

        $days = collect(range(6, 0))->map(fn ($i) => now()->subDays($i)->startOfDay());

        $labels = $days->map(fn (Carbon $d) => self::DIAS_ABREV[(int) $d->dayOfWeek])->all();

        $entradas = $days->map(fn (Carbon $d) => PatioEntry::where('tenant_id', $tenantId)
            ->where('direction', PatioEntry::DIRECTION_ENTRADA)
            ->whereBetween('arrived_at', [$d, $d->copy()->endOfDay()])
            ->count())->all();

        $saidas = $days->map(fn (Carbon $d) => PatioEntry::where('tenant_id', $tenantId)
            ->where('direction', PatioEntry::DIRECTION_SAIDA)
            ->whereBetween('arrived_at', [$d, $d->copy()->endOfDay()])
            ->count())->all();

        return [
            'datasets' => [
                [
                    'label' => 'Entradas',
                    'data' => $entradas,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16,185,129,.15)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
                [
                    'label' => 'Saídas',
                    'data' => $saidas,
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245,158,11,.12)',
                    'tension' => 0.35,
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['display' => true, 'labels' => ['color' => '#94a3b8']],
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
