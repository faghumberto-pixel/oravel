<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class GestaoAtivos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cube-transparent';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationLabel = 'Gestão de Ativos';

    protected static ?string $title = 'Gestão de Ativos';

    protected static string $view = 'filament.pages.gestao-ativos';

    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = true;
}
