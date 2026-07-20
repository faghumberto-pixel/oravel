<?php

namespace App\Filament\Central\Widgets;

use App\Filament\Central\Resources\SalesLeadResource;
use App\Models\SalesLead;
use Filament\Widgets\Widget;

/**
 * Mapa "Prospecção Planejada" -- mesmo padrão do SalesLeadMapWidget, mas
 * filtrado so' pros leads ainda no estágio inicial (Prospecção), pedido
 * explícito do usuário: um segundo mapa ao lado do Mapa Comercial mostrando
 * onde ainda ha' trabalho de prospecção a fazer, separado dos leads já
 * qualificados/em andamento no funil.
 */
class ProspectingMapWidget extends Widget
{
    protected static string $view = 'filament.central.widgets.prospecting-map-widget';

    /**
     * Chamado via $wire.refreshMapData() -- mesmo motivo de
     * SalesLeadMapWidget: o mapa vive em wire:ignore (Leaflet), Livewire
     * nunca redesenha os marcadores sozinho.
     */
    public function refreshMapData(): array
    {
        return $this->getLeads();
    }

    public function getLeads(): array
    {
        return SalesLead::where('pipeline_stage', SalesLead::STAGE_PROSPECCAO)
            ->whereNotNull('latitude')
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
