<?php

namespace App\Filament\Central\Resources\SalesLeadResource\Widgets;

use App\Models\SalesLead;
use Filament\Widgets\ChartWidget;

class LeadsByStageChart extends ChartWidget
{
    protected static ?string $heading = 'Leads por Estágio do Funil';

    protected static ?string $maxHeight = '260px';

    /**
     * Mesmos tons usados em App\Support\CrmPalette::stage() (classes
     * Tailwind) -- CrmPalette não expõe 'hex' pra estágio (só pra
     * segmento), então repetido aqui em hex pro Chart.js.
     */
    private const STAGE_HEX = [
        SalesLead::STAGE_PROSPECCAO => '#2563eb',
        SalesLead::STAGE_CONTATO_QUALIFICADO => '#4f46e5',
        SalesLead::STAGE_DEMONSTRACAO_REALIZADA => '#9333ea',
        SalesLead::STAGE_PROPOSTA_ENVIADA => '#f97316',
        SalesLead::STAGE_GANHO => '#059669',
        SalesLead::STAGE_PERDIDO => '#dc2626',
    ];

    protected function getData(): array
    {
        $labels = SalesLead::stageLabels();

        $counts = SalesLead::query()
            ->selectRaw('pipeline_stage, count(*) as total')
            ->groupBy('pipeline_stage')
            ->pluck('total', 'pipeline_stage');

        $rows = $counts->keys()->map(fn (string $stage) => $labels[$stage] ?? $stage)->all();
        $colors = $counts->keys()->map(fn (string $stage) => self::STAGE_HEX[$stage] ?? '#6b7280')->all();

        return [
            'datasets' => [
                [
                    'label' => 'Leads',
                    'data' => $counts->values()->all(),
                    'backgroundColor' => $colors,
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $rows,
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
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}
