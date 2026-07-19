<?php

namespace App\Filament\Central\Pages;

use Filament\Pages\Page;

class Programacao extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Programação';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $title = 'Programação — CRM Comercial';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.central.pages.programacao';
}
