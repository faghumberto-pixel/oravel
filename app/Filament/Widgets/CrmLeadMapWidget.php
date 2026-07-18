<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\CrmLeadResource;
use App\Models\Client;
use App\Models\CrmLead;
use App\Support\Tenancy;
use Filament\Widgets\Widget;

class CrmLeadMapWidget extends Widget
{
    protected static string $view = 'filament.widgets.crm-lead-map-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * Chamado via $wire.refreshMapData() do Alpine (ver blade) -- o mapa
     * vive dentro de wire:ignore (Leaflet nao pode ter o DOM remexido pelo
     * Livewire), entao Livewire nunca re-renderiza os marcadores sozinho.
     * Sem esse metodo pra buscar dado fresco sob demanda, cliente/lead
     * novo so' aparecia no mapa apos recarregar a pagina inteira.
     *
     * @return array{clientes: array, leads: array}
     */
    public function refreshMapData(): array
    {
        return [
            'clientes' => $this->getClients(),
            'leads' => $this->getLeads(),
        ];
    }

    /**
     * Leads com coordenadas cadastradas (marcador laranja no mapa).
     */
    public function getLeads(): array
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return [];
        }

        $user = auth()->user();

        $query = CrmLead::where('tenant_id', $tenant->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude');

        if (! $user->canSeeAllCrmLeads()) {
            $query->where('assigned_user_id', $user->id);
        }

        return $query->get(['id', 'name', 'company_name', 'stage', 'latitude', 'longitude'])
            ->map(fn (CrmLead $lead) => [
                'id' => $lead->id,
                'name' => $lead->name,
                'company_name' => $lead->company_name,
                'stage_label' => CrmLead::stageLabels()[$lead->stage] ?? $lead->stage,
                'latitude' => $lead->latitude,
                'longitude' => $lead->longitude,
                'url' => CrmLeadResource::getUrl('edit', ['record' => $lead]),
            ])
            ->all();
    }

    /**
     * Clientes com coordenadas cadastradas (marcador azul no mapa). Client
     * ja tem latitude/longitude geocodificadas a partir do CEP (mesmo
     * pipeline do Mapa de Equipamentos) -- respeita a trava comercial:
     * se o plano do tenant nao inclui Clientes, nao mostra nenhum aqui.
     */
    public function getClients(): array
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return [];
        }

        if (! auth()->user()->can('viewAny', Client::class)) {
            return [];
        }

        return Client::where('tenant_id', $tenant->id)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get(['id', 'name', 'city', 'uf', 'latitude', 'longitude'])
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'name' => $client->name,
                'city' => $client->city,
                'uf' => $client->uf,
                'latitude' => $client->latitude,
                'longitude' => $client->longitude,
                'url' => ClientResource::getUrl('edit', ['record' => $client]),
            ])
            ->all();
    }
}
