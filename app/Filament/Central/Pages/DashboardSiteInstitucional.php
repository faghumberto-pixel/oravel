<?php

namespace App\Filament\Central\Pages;

use App\Filament\Central\Widgets\Site\SiteClicksTable;
use App\Filament\Central\Widgets\Site\SiteSourcesTable;
use App\Filament\Central\Widgets\Site\SiteStatsOverview;
use App\Filament\Central\Widgets\Site\SiteTopPagesTable;
use App\Filament\Central\Widgets\Site\SiteVisitsChart;
use App\Support\WebAnalytics;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;

/**
 * Painel do SITE INSTITUCIONAL (oravel.com.br), separado de propósito de
 * "Acessos e Visitantes" (que mede o app): dados em web_visits/web_pageviews/
 * web_events, coletados pelo script public/t.js, nunca em site_visits.
 */
class DashboardSiteInstitucional extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'site-institucional';

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationGroup = 'Site Institucional';

    protected static ?string $navigationLabel = 'Painel do Site';

    protected static ?string $title = 'Site Institucional — Acessos';

    protected static ?int $navigationSort = 0;

    public function filtersForm(Form $form): Form
    {
        return $form->schema([
            Select::make('period')->label('Período')->options(WebAnalytics::periods())->default('7')->native(false)->live(),
        ])->columns(4);
    }

    public function getWidgets(): array
    {
        return [
            SiteStatsOverview::class,
            SiteVisitsChart::class,
            SiteTopPagesTable::class,
            SiteSourcesTable::class,
            SiteClicksTable::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
