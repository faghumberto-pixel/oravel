<?php

namespace App\Filament\Pages;

use App\Models\CrmLead;
use Filament\Pages\Page;

class CrmMapa extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationLabel = 'Mapa de Leads';

    protected static ?string $title = 'Mapa de Leads';

    protected static ?string $navigationGroup = 'Comercial';

    protected static string $view = 'filament.pages.crm-mapa';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', CrmLead::class);
    }
}
