<?php

namespace App\Filament\Widgets;

use App\Models\CrmLead;
use App\Support\Tenancy;
use Filament\Widgets\Widget;

class CrmLeadMapWidget extends Widget
{
    protected static string $view = 'filament.widgets.crm-lead-map-widget';

    protected int|string|array $columnSpan = 'full';

    /**
     * Leads com coordenadas cadastradas. So Leads por enquanto -- Client nao
     * tem colunas de lat/lng ainda (precisaria de geocoding, deixado de fora
     * de proposito).
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

        if (! $user->isAdmin()) {
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
                'url' => \App\Filament\Resources\CrmLeadResource::getUrl('edit', ['record' => $lead]),
            ])
            ->all();
    }
}
