<?php

namespace App\Filament\Pages;

use App\Dashboards\ModuleDashboardPage;

class VisaoGeralPlanejamento extends ModuleDashboardPage
{
    protected static string $dashboardKey = 'planejamento';

    protected static ?string $navigationGroup = 'PMP';

    protected static ?string $slug = 'visao-geral-planejamento';
}
