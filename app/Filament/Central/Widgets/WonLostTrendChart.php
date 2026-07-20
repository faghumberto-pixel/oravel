<?php

namespace App\Filament\Central\Widgets;

use App\Models\SalesLead;
use Filament\Widgets\ChartWidget;

class WonLostTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Ganhos vs Perdidos (últimos 6 meses)';

    protected static ?string $maxHeight = '260px';

    // Mesmo motivo documentado em LeadsCreatedTrendChart: translatedFormat()
    // nao respeita o locale do app de forma confiavel.
    private const MESES = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];

    protected function getData(): array
    {
        // updated_at como proxy de "quando fechou" -- nao ha coluna dedicada
        // (closed_at) em sales_leads hoje. Imperfeito (qualquer edicao no
        // lead mexe em updated_at), mas e' o dado real mais proximo
        // disponivel, sem inventar numero nenhum.
        $labels = [];
        $ganhos = [];
        $perdidos = [];

        foreach (range(5, 0) as $monthsAgo) {
            $month = now()->subMonths($monthsAgo);
            $labels[] = self::MESES[$month->month].'/'.$month->format('y');

            $ganhos[] = SalesLead::query()
                ->where('pipeline_stage', SalesLead::STAGE_GANHO)
                ->whereYear('updated_at', $month->year)
                ->whereMonth('updated_at', $month->month)
                ->count();

            $perdidos[] = SalesLead::query()
                ->where('pipeline_stage', SalesLead::STAGE_PERDIDO)
                ->whereYear('updated_at', $month->year)
                ->whereMonth('updated_at', $month->month)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ganhos',
                    'data' => $ganhos,
                    'backgroundColor' => '#059669',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Perdidos',
                    'data' => $perdidos,
                    'backgroundColor' => '#dc2626',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
