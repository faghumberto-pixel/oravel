<?php

namespace App\Relacionamento\Filament\Widgets;

use App\Filament\Central\Widgets\Site\SiteTopPagesTable;
use App\Models\WebVisit;

/** SiteTopPagesTable olhando o oravel.org (com os filtros do painel). */
class OrgTopPagesTable extends SiteTopPagesTable
{
    protected static ?string $heading = 'Páginas mais acessadas no oravel.org';

    protected function site(): string
    {
        return WebVisit::SITE_ORG;
    }
}
