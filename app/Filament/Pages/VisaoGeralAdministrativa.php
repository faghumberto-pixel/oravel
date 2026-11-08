<?php

namespace App\Filament\Pages;

use App\Dashboards\ModuleDashboardPage;

class VisaoGeralAdministrativa extends ModuleDashboardPage
{
    protected static string $dashboardKey = 'administrativo';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $slug = 'visao-geral-administrativo';
}
