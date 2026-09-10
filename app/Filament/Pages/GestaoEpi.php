<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class GestaoEpi extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationLabel = 'Gestão de EPI';

    protected static ?string $title = 'Gestão de EPI';

    protected static string $view = 'filament.pages.gestao-epi';

    protected static ?int $navigationSort = 30;

    protected static bool $shouldRegisterNavigation = true;
}
