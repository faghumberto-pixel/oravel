<?php

namespace App\Filament\Pages;

use App\Dashboards\ModuleDashboardPage;

/** Fica dentro do subgrupo "Frota" do menu Logística (âncora: LogisticaFrota). */
class VisaoGeralFrota extends ModuleDashboardPage
{
    protected static string $dashboardKey = 'frota';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationParentItem = 'Frota';

    protected static ?string $slug = 'visao-geral-frota';
}
