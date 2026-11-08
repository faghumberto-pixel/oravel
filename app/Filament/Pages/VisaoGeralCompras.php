<?php

namespace App\Filament\Pages;

use App\Dashboards\ModuleDashboardPage;

class VisaoGeralCompras extends ModuleDashboardPage
{
    protected static string $dashboardKey = 'compras';

    protected static ?string $navigationGroup = 'Materiais e Peças';

    protected static ?string $slug = 'visao-geral-compras';
}
