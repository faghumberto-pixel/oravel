<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Nr13Stats;
use App\Models\Nr13Document;
use Filament\Pages\Page;

/**
 * Página-âncora do menu "Conformidade NR-13" (navigationParentItem de
 * Nr13InspectionPeriodicityResource aponta pra este navigationLabel, mesmo mecanismo de
 * App\Filament\Pages\GestaoComercial -- max 2 níveis, sem Clusters). Sem view customizada:
 * a view padrão do Filament já renderiza getHeaderWidgets() sozinha.
 */
class Nr13ComplianceDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $navigationLabel = 'Conformidade NR-13';

    protected static ?string $title = 'Conformidade NR-13';

    protected static string $view = 'filament.pages.nr13-compliance-dashboard';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', Nr13Document::class);
    }

    protected function getHeaderWidgets(): array
    {
        return [
            Nr13Stats::class,
        ];
    }
}
