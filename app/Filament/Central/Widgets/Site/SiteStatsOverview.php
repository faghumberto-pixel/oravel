<?php

namespace App\Filament\Central\Widgets\Site;

use App\Models\WebEvent;
use App\Models\WebVisit;
use App\Support\WebAnalytics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SiteStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $since = WebAnalytics::since($this->filters['period'] ?? '7');
        $visits = WebVisit::query()->where('started_at', '>=', $since);

        $total = (clone $visits)->count();
        $unique = (clone $visits)->distinct()->count('visitor_token');
        $pageviews = (int) (clone $visits)->sum('page_views');
        $avg = (int) (clone $visits)->where('page_views', '>', 0)->avg('duration_seconds');
        $bounced = (clone $visits)->where('page_views', '<=', 1)->where('duration_seconds', '<', 10)->count();
        $bounceRate = $total > 0 ? round(($bounced / $total) * 100) : 0;
        $clicks = WebEvent::query()->where('occurred_at', '>=', $since)->where('type', 'click')->count();
        $returning = (clone $visits)->where('is_returning', true)->count();

        return [
            Stat::make('Visitantes únicos', $unique)->description($total.' visita(s) no período')->descriptionIcon('heroicon-m-user-group')->color('primary'),
            Stat::make('Páginas vistas', $pageviews)->description($total > 0 ? number_format($pageviews / $total, 1, ',', '.').' por visita' : 'sem visitas')->descriptionIcon('heroicon-m-document-text')->color('info'),
            Stat::make('Tempo médio na visita', WebAnalytics::duration($avg))->description('Tempo ativo (minutos:segundos)')->descriptionIcon('heroicon-m-clock')->color('success'),
            Stat::make('Taxa de rejeição', $bounceRate.'%')->description('Saíram em menos de 10 s, sem navegar')->descriptionIcon('heroicon-m-arrow-uturn-left')->color($bounceRate > 70 ? 'danger' : 'warning'),
            Stat::make('Cliques em CTAs', $clicks)->description('WhatsApp, contato, planos e links')->descriptionIcon('heroicon-m-cursor-arrow-rays')->color('success'),
            Stat::make('Visitantes que voltaram', $returning)->description('Visitas de quem já tinha vindo antes')->descriptionIcon('heroicon-m-arrow-path')->color('gray'),
        ];
    }
}
