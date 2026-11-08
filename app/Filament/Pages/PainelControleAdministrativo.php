<?php

namespace App\Filament\Pages;

use App\Dashboards\ModuleDashboardPage;

class PainelControleAdministrativo extends ModuleDashboardPage
{
    protected static string $dashboardKey = 'administrativo';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $slug = 'painel-controle-administrativo';
}
