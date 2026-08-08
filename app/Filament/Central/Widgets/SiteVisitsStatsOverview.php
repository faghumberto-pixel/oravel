<?php

namespace App\Filament\Central\Widgets;

use App\Models\SiteVisit;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class SiteVisitsStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $desde = Carbon::now()->subDays(30);
        $visitas = SiteVisit::query()->where('started_at', '>=', $desde);

        $totalAcessos = (clone $visitas)->count();
        $visitantesUnicos = (clone $visitas)->distinct('visitor_token')->count('visitor_token');
        $tempoMedio = (int) (clone $visitas)->avg('duration_seconds');
        $comUtm = (clone $visitas)->whereNotNull('utm_source')->count();
        $percentualUtm = $totalAcessos > 0 ? round(($comUtm / $totalAcessos) * 100) : 0;

        return [
            Stat::make('Acessos (30 dias)', $totalAcessos)
                ->description('Sessões de visita registradas')
                ->descriptionIcon('heroicon-m-cursor-arrow-rays')
                ->color('primary'),

            Stat::make('Visitantes Únicos', $visitantesUnicos)
                ->description('Últimos 30 dias')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('crmBlue'),

            Stat::make('Tempo Médio de Visita', sprintf('%d:%02d', intdiv($tempoMedio, 60), $tempoMedio % 60))
                ->description('Minutos:segundos por sessão')
                ->descriptionIcon('heroicon-m-clock')
                ->color('crmPurple'),

            Stat::make('Acessos com Origem Rastreada', "{$percentualUtm}%")
                ->description('Chegaram via UTM (campanha/canal)')
                ->descriptionIcon('heroicon-m-megaphone')
                ->color($percentualUtm > 0 ? 'success' : 'gray'),
        ];
    }
}
