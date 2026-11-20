<?php

namespace App\Relacionamento\Filament\Widgets;

use App\Filament\Central\Widgets\Site\SiteSourcesTable;
use App\Models\WebVisit;

/** SiteSourcesTable olhando o oravel.org (com os filtros do painel). */
class OrgSourcesTable extends SiteSourcesTable
{
    protected static ?string $heading = 'De onde vêm as visitas ao oravel.org';

    protected function site(): string
    {
        return WebVisit::SITE_ORG;
    }
}
