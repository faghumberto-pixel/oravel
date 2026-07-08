<?php

namespace App\Filament\Pages;

use App\Models\MaintenanceOrder;
use Filament\Pages\Page;

class Relatorios extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Relatórios';

    protected static ?string $title = 'Relatórios';

    protected static string $view = 'filament.pages.relatorios';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }
}
