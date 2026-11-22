<?php

namespace App\Filament\Pages;

use App\Domain\Intelligence\Models\IntelligenceSnapshot;
use App\Domain\Intelligence\Services\EvolucaoService;
use App\Filament\Resources\IntelligenceImportResource;
use App\Support\Tenancy;
use Filament\Pages\Page;

/** Intelligence › Dashboard Executivo: como a empresa está e como evoluiu entre os snapshots. Chave de contrato: menu_intelligence_dashboard. */
class IntelligenceDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationGroup = 'Intelligence';

    protected static ?string $navigationLabel = 'Dashboard Executivo';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Dashboard Executivo';

    protected static ?string $slug = 'intelligence-dashboard';

    protected static string $view = 'filament.pages.intelligence-dashboard';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', IntelligenceSnapshot::class);
    }

    /** @return ?array<string, mixed> */
    public function resumo(): ?array
    {
        $tenant = Tenancy::current();

        return $tenant ? app(EvolucaoService::class)->resumo($tenant->id) : null;
    }

    public function urlImportacoes(): string
    {
        return IntelligenceImportResource::getUrl();
    }
}
