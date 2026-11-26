<?php

namespace App\Filament\Pages;

use App\Dashboards\ModuleDashboardPage;

/** Painel de gestão comercial (CRM): vale para o pacote Vendas e para o módulo Comercial do ERP. */
class VisaoGeralVendas extends ModuleDashboardPage
{
    protected static string $dashboardKey = 'vendas';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $slug = 'visao-geral-vendas';
}
