<?php

namespace App\Filament\Pages;

use App\Dashboards\ModuleDashboardPage;

class VisaoGeralAtivos extends ModuleDashboardPage
{
    protected static string $dashboardKey = 'ativos';

    protected static ?string $navigationGroup = 'Ativos';

    protected static ?string $navigationLabel = 'Visão geral';

    protected static ?string $slug = 'visao-geral-ativos';
}
