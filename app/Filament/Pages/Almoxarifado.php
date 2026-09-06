<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Almoxarifado extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationLabel = 'Gestão Almoxarifado';

    protected static ?string $title = 'Gestão Almoxarifado';

    protected static string $view = 'filament.pages.almoxarifado';

    protected static ?int $navigationSort = 10;

    protected static bool $shouldRegisterNavigation = true;
}
