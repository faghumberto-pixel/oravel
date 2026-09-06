<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

/**
 * Page "hub" (sem dados proprios) que serve so' de item pai no menu
 * Comercial -- mesmo padrao de App\Filament\Pages\Almoxarifado. Agenda
 * Comercial, Funil de Vendas, Mapa Comercial e Leads (CRM) apontam pra ela
 * via $navigationParentItem = 'Gestão CRM'.
 */
class GestaoCrm extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationLabel = 'Gestão CRM';

    protected static ?string $title = 'Gestão CRM';

    protected static ?int $navigationSort = -19;

    protected static string $view = 'filament.pages.gestao-crm';

    protected static bool $shouldRegisterNavigation = true;
}
