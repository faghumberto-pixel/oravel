<?php

namespace App\Relacionamento\Filament\Widgets;

use App\Filament\Central\Widgets\Site\SiteStatsOverview;
use App\Models\WebVisit;

/** SiteStatsOverview olhando o oravel.org (com os filtros do painel). */
class OrgStatsOverview extends SiteStatsOverview
{
    protected function site(): string
    {
        return WebVisit::SITE_ORG;
    }
}
