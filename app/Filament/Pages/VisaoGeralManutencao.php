<?php

namespace App\Filament\Pages;

use App\Dashboards\ModuleDashboardPage;

class VisaoGeralManutencao extends ModuleDashboardPage
{
    protected static string $dashboardKey = 'manutencao';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $slug = 'visao-geral-manutencao';
}
