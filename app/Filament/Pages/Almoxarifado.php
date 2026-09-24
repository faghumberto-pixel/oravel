<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Page "hub" (sem dados proprios) que serve so' de item pai no menu Ativos
 * e Materiais. Intencionalmente sem canAccess() por Contrato: esconder o
 * hub esconderia TODOS os filhos junto, mesmo os que o tenant tem
 * contratado (mesmo raciocinio de App\Filament\Pages\GestaoComercial).
 */
class Almoxarifado extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationLabel = 'Gestão de Estoque';

    protected static ?string $title = 'Gestão de Estoque';

    protected static string $view = 'filament.pages.almoxarifado';

    protected static ?int $navigationSort = 10;

    protected static bool $shouldRegisterNavigation = true;
}
