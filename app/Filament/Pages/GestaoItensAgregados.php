<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Page "hub" (sem dados proprios), mesmo padrao de GestaoEpi/GestaoAtivos.
 * Sem canAccess() por Contrato: esconder o hub esconderia os filhos junto.
 */
class GestaoItensAgregados extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationGroup = 'Itens Agregados';

    protected static ?string $navigationLabel = 'Acessórios e Componentes';

    protected static ?string $title = 'Acessórios e Componentes';

    protected static string $view = 'filament.pages.gestao-itens-agregados';

    protected static ?int $navigationSort = 0;

    protected static bool $shouldRegisterNavigation = true;
}
