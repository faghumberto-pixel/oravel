<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Relatorios extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Relatórios';

    protected static ?string $title = 'Relatórios';

    protected static string $view = 'filament.pages.relatorios';
}
