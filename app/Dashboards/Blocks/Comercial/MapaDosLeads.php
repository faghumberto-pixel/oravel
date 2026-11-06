<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Filament\Pages\CrmMapa;
use App\Models\CrmLead;

class MapaDosLeads extends ComercialBlock
{
    use ComercialIaHelpers;

    public static function key(): string
    {
        return 'comercial.mapa_leads';
    }

    public static function title(): string
    {
        return 'Mapa comercial';
    }

    public static function subtitle(): string
    {
        return 'Onde estão as oportunidades; a cor é a etapa do funil';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_crm_leads';
    }

    public static function sourceModel(): string
    {
        return CrmLead::class;
    }

    public function data(array $config): array
    {
        $leads = CrmLead::query()->whereNotNull('latitude')->whereNotNull('longitude')->get(['name', 'company_name', 'stage', 'estimated_value', 'city', 'latitude', 'longitude']);
        $cores = $this->coresDasEtapas();
        $rotulos = $this->etapas();

        $pontos = $leads->map(fn ($l) => [
            (float) $l->latitude, (float) $l->longitude, $cores[$l->stage] ?? '#64748b', $l->company_name ?: $l->name,
            ($rotulos[$l->stage] ?? $l->stage).' · '.$this->compacto((float) $l->estimated_value).($l->city ? ' · '.$l->city : ''),
        ])->all();

        $legenda = $leads->pluck('stage')->unique()->map(fn ($s) => [$rotulos[$s] ?? $s, $cores[$s] ?? '#64748b'])->values()->all();

        return Payload::map($pontos, $legenda, CrmMapa::getUrl(), 'Abrir o mapa comercial');
    }
}
