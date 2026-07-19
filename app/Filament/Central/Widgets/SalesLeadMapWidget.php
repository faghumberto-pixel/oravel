<?php

namespace App\Filament\Central\Widgets;

use App\Filament\Central\Resources\SalesLeadResource;
use App\Models\SalesLead;
use Filament\Widgets\Widget;

class SalesLeadMapWidget extends Widget
{
    protected static string $view = 'filament.central.widgets.sales-lead-map-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * Chamado via $wire.refreshMapData() -- mesmo motivo de
     * CrmLeadMapWidget: o mapa vive em wire:ignore (Leaflet), Livewire
     * nunca redesenha os marcadores sozinho.
     */
    public function refreshMapData(): array
    {
        return $this->getLeads();
    }

    public function getLeads(): array
    {
        return SalesLead::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'company_name', 'pipeline_stage', 'segment', 'latitude', 'longitude'])
            ->map(fn (SalesLead $lead) => [
                'id' => $lead->id,
                'company_name' => $lead->company_name,
                'stage_label' => SalesLead::stageLabels()[$lead->pipeline_stage] ?? $lead->pipeline_stage,
                'latitude' => $lead->latitude,
                'longitude' => $lead->longitude,
                'url' => SalesLeadResource::getUrl('edit', ['record' => $lead]),
            ])
            ->all();
    }
}
