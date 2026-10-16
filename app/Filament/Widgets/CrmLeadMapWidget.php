<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ClientResource;
use App\Filament\Resources\CrmContaResource;
use App\Filament\Resources\CrmLeadResource;
use App\Models\Client;
use App\Models\CrmConta;
use App\Models\CrmLead;
use App\Support\CrmApiPresenter;
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

        return $query->with('etapa')->get(['id', 'name', 'company_name', 'stage', 'etapa_id', 'latitude', 'longitude'])
            ->map(fn (CrmLead $lead) => [
                'id' => $lead->id,
                'name' => $lead->name,
                'company_name' => $lead->company_name,
                'stage_label' => $lead->etapaNome(),
                'latitude' => $lead->latitude,
                'longitude' => $lead->longitude,
                'url' => CrmLeadResource::getUrl('edit', ['record' => $lead]),
            ])
            ->all();
    }

    /**
     * "Clientes" do mapa (marcador azul): as Contas do CRM com coordenadas e, só para quem tem o módulo
     * de Clientes do ERP, os Clientes que ainda não estão ligados a uma conta.
     */
    public function getClients(): array
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return [];
        }

        $user = auth()->user();
        $itens = [];
        $clientesJaNoMapa = [];

        if ($user->can('viewAny', CrmConta::class)) {
            foreach (CrmApiPresenter::contasVisiveis($user)->whereNotNull('latitude')->whereNotNull('longitude')->get() as $conta) {
                $itens[] = [
                    'id' => $conta->id, 'name' => $conta->nome, 'city' => $conta->cidade, 'uf' => $conta->uf,
                    'latitude' => $conta->latitude, 'longitude' => $conta->longitude,
                    'url' => CrmContaResource::getUrl('edit', ['record' => $conta]),
                ];
                $conta->client_id && $clientesJaNoMapa[] = $conta->client_id;
            }
        }

        if ($tenant->hasFeature('tabela_clients') && $user->can('viewAny', Client::class)) {
            $clientes = Client::where('tenant_id', $tenant->id)->whereNotNull('latitude')->whereNotNull('longitude')
                ->whereNotIn('id', $clientesJaNoMapa)->get(['id', 'name', 'city', 'uf', 'latitude', 'longitude']);

            foreach ($clientes as $client) {
                $itens[] = [
                    'id' => $client->id, 'name' => $client->name, 'city' => $client->city, 'uf' => $client->uf,
                    'latitude' => $client->latitude, 'longitude' => $client->longitude,
                    'url' => ClientResource::getUrl('edit', ['record' => $client]),
                ];
            }
        }

        return $itens;
    }
}
