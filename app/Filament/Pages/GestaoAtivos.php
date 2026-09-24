<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Page "hub" (sem dados proprios) que serve so' de item pai no menu
 * Ativos e Materiais -- mesmo padrao de App\Filament\Pages\GestaoComercial.
 * Intencionalmente sem canAccess() por Contrato: esconder o hub esconderia
 * TODOS os filhos junto, mesmo os que o tenant tem contratado.
 */
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
