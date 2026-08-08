<?php

namespace App\Filament\Central\Widgets;

use App\Models\SiteVisit;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Acessos reais dos ultimos 7 dias (site_visits, ver TrackSiteVisit) --
 * antes era dado 100% inventado (comentario "dados simulados") e por isso
 * excluido de DashboardSaaS::getWidgets(). Agora vive em
 * DashboardVisitantes com dado de verdade.
 */
class EngagementChart extends ChartWidget
{
    protected static ?string $heading = 'Acessos nos Últimos 7 Dias';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '275px';

    private const DIAS_SEMANA = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

    protected function getData(): array
    {
        $start = Carbon::now()->subDays(6)->startOfDay();

        $porDia = SiteVisit::query()
            ->selectRaw('DATE(started_at) as dia, COUNT(*) as total')
            ->where('started_at', '>=', $start)
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $labels = [];
        $data = [];

        for ($i = 0; $i < 7; $i++) {
            $dia = $start->copy()->addDays($i);
            $labels[] = self::DIAS_SEMANA[(int) $dia->format('w')];
            $data[] = (int) ($porDia[$dia->toDateString()] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Acessos',
                    'data' => $data,
                    'backgroundColor' => '#8b5cf6',
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
