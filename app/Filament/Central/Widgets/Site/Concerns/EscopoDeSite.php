<?php

namespace App\Filament\Central\Widgets\Site\Concerns;

use App\Models\WebVisit;
use Illuminate\Database\Eloquent\Builder;

/** Qual site os blocos do painel de visitas olham. O padrao e' o institucional; o painel do oravel.org troca. */
trait EscopoDeSite
{
    protected function site(): string
    {
        return WebVisit::SITE_INSTITUCIONAL;
    }

    /** Escopo unico das visitas do painel: o site + os filtros da pagina (dispositivo, estado, origem, retorno). */
    protected function visitas(Builder $query): Builder
    {
        return $query->doSite($this->site())->filtrado($this->filters ?? []);
    }
}
