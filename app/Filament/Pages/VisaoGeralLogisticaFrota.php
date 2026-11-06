<?php

namespace App\Filament\Pages;

use App\Dashboards\ModuleDashboardPage;

/** Fica no menu "Gestão de Frota": onde estão os veículos, com quem estão e como estão sendo usados. */
class VisaoGeralLogisticaFrota extends ModuleDashboardPage
{
    protected static string $dashboardKey = 'frota_logistica';

    protected static ?string $navigationGroup = 'Gestão de Frota';

    protected static ?string $slug = 'visao-geral-logistica-frota';
}
