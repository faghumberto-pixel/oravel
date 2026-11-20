<?php

namespace App\Relacionamento\Filament\Widgets;

use App\Filament\Central\Widgets\Site\SiteClicksTable;
use App\Models\WebVisit;

/** SiteClicksTable olhando o oravel.org (com os filtros do painel). */
class OrgClicksTable extends SiteClicksTable
{
    protected static ?string $heading = 'Cliques no oravel.org (downloads, links externos e botões)';

    protected function site(): string
    {
        return WebVisit::SITE_ORG;
    }
}
