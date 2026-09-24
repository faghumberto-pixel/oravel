<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Page "hub" (sem dados proprios), mesmo padrao de GestaoComercial.
 * Intencionalmente sem canAccess() por Contrato: esconder o hub esconderia
 * TODOS os filhos junto, mesmo os que o tenant tem contratado.
 */
class GestaoCompras extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationLabel = 'Gestão de Compras';

    protected static ?string $title = 'Gestão de Compras';

    protected static string $view = 'filament.pages.gestao-compras';

    protected static ?int $navigationSort = 20;

    protected static bool $shouldRegisterNavigation = true;
}
