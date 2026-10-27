<?php

namespace App\Filament\Pages;

use App\Dashboards\ModuleDashboardPage;

class VisaoGeralMateriais extends ModuleDashboardPage
{
    protected static string $dashboardKey = 'materiais';

    protected static ?string $navigationGroup = 'Materiais e Peças';

    protected static ?string $navigationLabel = 'Visão geral';

    protected static ?string $slug = 'visao-geral-materiais';
}
