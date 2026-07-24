<?php

namespace App\Filament\Widgets;

use App\Models\PatioEntry;
use App\Support\Tenancy;
use Filament\Widgets\ChartWidget;

/**
 * Motivo das movimentações de portaria (últimos 30 dias) -- mesma ideia do
 * donut "Status das O.S. Atuais" do Dashboard PMP.
 */
class PatioReasonDonutChart extends ChartWidget
{
    protected static ?string $heading = 'Movimentações por Motivo (30 dias)';

    protected static ?string $maxHeight = '200px';

    protected int|string|array $columnSpan = ['md' => 1];

    private const CORES = [
        PatioEntry::REASON_MOBILIZACAO => '#3987e5',
        PatioEntry::REASON_DESMOBILIZACAO => '#c98500',
        PatioEntry::REASON_VISITA => '#9085e9',
        PatioEntry::REASON_FORNECEDOR => '#199e70',
        PatioEntry::REASON_ENTREGA_PECAS => '#0d9488',
        PatioEntry::REASON_SAIDA_EXTERNA => '#64748b',
        PatioEntry::REASON_TRANSFERENCIA => '#e66767',
        PatioEntry::REASON_OUTRO => '#94a3b8',
    ];

    protected function getData(): array
    {
        $labels = PatioEntry::reasonLabels();

        $counts = PatioEntry::where('tenant_id', Tenancy::current()?->id)
            ->where('arrived_at', '>=', now()->subDays(30))
            ->selectRaw('reason, count(*) as total')
            ->groupBy('reason')
            ->orderByDesc('total')
            ->pluck('total', 'reason');

        $data = [];
        $backgroundColor = [];
        $outLabels = [];

        foreach ($counts as $reason => $total) {
            $data[] = $total;
            $backgroundColor[] = self::CORES[$reason] ?? '#94a3b8';
            $outLabels[] = $labels[$reason] ?? $reason;
        }

        return [
            'datasets' => [[
                'data' => $data,
                'backgroundColor' => $backgroundColor,
                'borderWidth' => 0,
            ]],
            'labels' => $outLabels,
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
