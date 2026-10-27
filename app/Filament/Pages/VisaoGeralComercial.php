<?php

namespace App\Filament\Pages;

use App\Dashboards\ModuleDashboardPage;

class VisaoGeralComercial extends ModuleDashboardPage
{
    protected static string $dashboardKey = 'comercial';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationLabel = 'Visão geral';

    protected static ?string $slug = 'visao-geral-comercial';
}
