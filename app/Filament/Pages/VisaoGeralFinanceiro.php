<?php

namespace App\Filament\Pages;

use App\Dashboards\ModuleDashboardPage;

class VisaoGeralFinanceiro extends ModuleDashboardPage
{
    protected static string $dashboardKey = 'financeiro';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?string $navigationLabel = 'Visão geral';

    protected static ?string $slug = 'visao-geral-financeiro';
}
