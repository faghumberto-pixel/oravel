<?php

namespace App\Relacionamento\Filament\Pages;

use App\Models\WebVisit;
use App\Relacionamento\Filament\Widgets\OrgClicksTable;
use App\Relacionamento\Filament\Widgets\OrgFerramentasTable;
use App\Relacionamento\Filament\Widgets\OrgRelacionamentoStats;
use App\Relacionamento\Filament\Widgets\OrgSourcesTable;
use App\Relacionamento\Filament\Widgets\OrgStatsOverview;
use App\Relacionamento\Filament\Widgets\OrgTopPagesTable;
use App\Relacionamento\Filament\Widgets\OrgVisitsChart;
use App\Support\WebAnalytics;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Painel das visitas ao oravel.org (conteúdo): mesmos blocos do painel do site institucional, só que olhando o site
 * oravel.org, com filtros de período, dispositivo, estado, origem e visitantes novos/que voltaram. Os blocos de
 * ferramentas e de pessoas vêm do Relacionamento e só respeitam o período.
 */
class DashboardOrg extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'oravel-org';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Relacionamento Oravel';

    protected static ?string $navigationLabel = 'Painel do oravel.org';

    protected static ?string $title = 'oravel.org — Visitas';

    protected static ?int $navigationSort = 5;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Conta só quem aceitou o aviso de privacidade do site. A audiência total, sem identificação, fica no Plausible.';
    }

    public function filtersForm(Form $form): Form
    {
        $org = fn () => WebVisit::query()->doSite(WebVisit::SITE_ORG);

        return $form->schema([
            Select::make('period')->label('Período')->options(WebAnalytics::periods())->default('7')->native(false)->live(),
            Select::make('device')->label('Dispositivo')->placeholder('Todos')->native(false)->live()
                ->options(['desktop' => 'Desktop', 'mobile' => 'Celular', 'tablet' => 'Tablet']),
            Select::make('uf')->label('Estado')->placeholder('Todos')->native(false)->live()->searchable()
                ->options(fn () => $org()->whereNotNull('state')->distinct()->orderBy('state')->pluck('state', 'state')->all()),
            Select::make('origem')->label('Origem')->placeholder('Todas')->native(false)->live()->searchable()
                ->options(function () use ($org) {
                    $hosts = $org()->whereNotNull('referrer_host')->distinct()->pluck('referrer_host');
                    $utms = $org()->whereNotNull('utm_source')->distinct()->pluck('utm_source');

                    return ['direto' => 'Acesso direto'] + $hosts->merge($utms)->unique()->sort()->mapWithKeys(fn ($o) => [$o => $o])->all();
                }),
            Select::make('retorno')->label('Visitantes')->placeholder('Todos')->native(false)->live()
                ->options(['novo' => 'Novos', 'voltou' => 'Que voltaram']),
        ])->columns(5);
    }

    public function getWidgets(): array
    {
        return [
            OrgStatsOverview::class,
            OrgVisitsChart::class,
            OrgTopPagesTable::class,
            OrgSourcesTable::class,
            OrgClicksTable::class,
            OrgFerramentasTable::class,
            OrgRelacionamentoStats::class,
        ];
    }

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
