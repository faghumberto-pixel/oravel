<?php

namespace App\Filament\Central\Widgets;

use App\Models\SalesLead;
use Filament\Widgets\ChartWidget;

class LeadsCreatedTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Novos Leads (últimos 6 meses)';

    protected static ?string $maxHeight = '260px';

    // translatedFormat() nao respeita o locale do app de forma confiavel
    // (confirmado: mostra "May" em vez de "Mai") -- mesmo padrao ja usado
    // em RevenueChart.php, nomes fixos em portugues.
    private const MESES = [1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'];

    protected function getData(): array
    {
        $labels = [];
        $data = [];

        foreach (range(5, 0) as $monthsAgo) {
            $month = now()->subMonths($monthsAgo);
            $labels[] = self::MESES[$month->month].'/'.$month->format('y');

            $data[] = SalesLead::query()
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Novos leads',
                    'data' => $data,
                    'borderColor' => '#0ea5e9',
                    'backgroundColor' => 'rgba(14, 165, 233, 0.15)',
                    'fill' => 'start',
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
