<?php

namespace App\Filament\Pages;

use App\Models\CrmLead;
use Filament\Pages\Page;

class CrmAgenda extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Agenda Comercial';

    protected static ?string $title = 'Agenda Comercial';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão CRM';

    protected static string $view = 'filament.pages.crm-agenda';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', CrmLead::class);
    }
}
