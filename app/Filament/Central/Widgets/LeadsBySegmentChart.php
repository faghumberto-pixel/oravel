<?php

namespace App\Filament\Central\Widgets;

use App\Models\Client;
use App\Models\SalesLead;
use App\Support\CrmPalette;
use Filament\Widgets\ChartWidget;

class LeadsBySegmentChart extends ChartWidget
{
    protected static ?string $heading = 'Leads por Segmento';

    protected static ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $counts = SalesLead::query()
            ->selectRaw('segment, count(*) as total')
            ->groupBy('segment')
            ->pluck('total', 'segment');

        $labels = $counts->keys()->map(fn (?string $segment) => $segment
            ? (Client::nicheLabels()[$segment] ?? $segment)
            : 'Sem segmento')->all();

        // Cor por segmento vem de App\Support\CrmPalette (mesma do Kanban/
        // tabelas) -- antes essa array de 7 cores fixas era atribuida por
        // ORDEM de aparicao no resultado da query, entao o mesmo segmento
        // podia mudar de cor dependendo dos dados (bug real, nao so' falta
        // de estilo).
        $colors = $counts->keys()->map(fn (?string $segment) => CrmPalette::segment($segment)['hex'])->all();

        return [
            'datasets' => [
                [
                    'data' => $counts->values()->all(),
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
