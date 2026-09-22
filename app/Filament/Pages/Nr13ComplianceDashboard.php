<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Nr13Stats;
use App\Models\Nr13Document;
use Filament\Pages\Page;

/**
 * Item do grupo de navegação EXCLUSIVO "Conformidade NR-13" (grupo plano, mesmo padrão do
 * grupo "PMP": PainelPmp/MaintenancePlanResource/etc, todos irmãos com navigationSort, sem
 * navigationParentItem -- ver CLAUDE.md, max 2 níveis via navigationParentItem, não Clusters;
 * aqui nem precisa do 2º nível). Sem view customizada além do necessário pra widgets: a view
 * padrão do Filament já renderiza getHeaderWidgets() sozinha.
 */
class Nr13ComplianceDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $navigationGroup = 'Conformidade NR-13';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;

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
