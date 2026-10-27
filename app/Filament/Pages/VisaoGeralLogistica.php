<?php

namespace App\Filament\Pages;

use App\Dashboards\ModuleDashboardPage;

class VisaoGeralLogistica extends ModuleDashboardPage
{
    protected static string $dashboardKey = 'logistica';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationLabel = 'Visão geral';

    protected static ?string $slug = 'visao-geral-logistica';
}
