<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Page "hub" (sem dados proprios) que serve so' de item pai no menu
 * Comercial -- mesmo padrao de App\Filament\Pages\Almoxarifado. Clientes,
 * Contratos, Assinaturas, Solicitacoes de Locacao, Propostas, Orcamentos
 * etc apontam pra ela via $navigationParentItem = 'Gestão Comercial'.
 */
class GestaoComercial extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationLabel = 'Gestão Comercial';

    protected static ?string $title = 'Gestão Comercial';

    protected static ?int $navigationSort = -20;

    protected static string $view = 'filament.pages.gestao-comercial';

    protected static bool $shouldRegisterNavigation = true;
}
