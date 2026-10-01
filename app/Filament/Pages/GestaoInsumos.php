<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/** Hub (sem dados proprios), mesmo padrao de GestaoItensAgregados. */
class GestaoInsumos extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'Itens Agregados';

    protected static ?string $navigationLabel = 'Insumos e Consumíveis';

    protected static ?string $title = 'Insumos e Consumíveis';

    protected static string $view = 'filament.pages.gestao-insumos';

    protected static ?int $navigationSort = 1;

    protected static bool $shouldRegisterNavigation = true;
}
