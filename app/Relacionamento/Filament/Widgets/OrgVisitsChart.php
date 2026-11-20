<?php

namespace App\Relacionamento\Filament\Widgets;

use App\Filament\Central\Widgets\Site\SiteVisitsChart;
use App\Models\WebVisit;

/** SiteVisitsChart olhando o oravel.org (com os filtros do painel). */
class OrgVisitsChart extends SiteVisitsChart
{
    protected static ?string $heading = 'Visitas por dia no oravel.org';

    protected function site(): string
    {
        return WebVisit::SITE_ORG;
    }
}
