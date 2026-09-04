<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Almoxarifado extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Ativos Materiais';

    protected static ?string $navigationLabel = 'Almoxarifado';

    protected static ?string $title = 'Almoxarifado';

    protected static string $view = 'filament.pages.almoxarifado';

    protected static ?int $navigationSort = 5;
}
