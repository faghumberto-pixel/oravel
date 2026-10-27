<?php

namespace App\Filament\Central\Resources\DashboardTemplateResource\Pages;

use App\Dashboards\Personalizacao\DashboardTemplateService;
use App\Filament\Central\Resources\DashboardTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDashboardTemplate extends CreateRecord
{
    protected static string $resource = DashboardTemplateResource::class;

    private bool $comoInicio = false;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->comoInicio = (bool) ($data['usar_como_inicio'] ?? false);
        unset($data['usar_como_inicio']);

        return $data;
    }

    /** Dashboard exclusivo já nasce atribuído à empresa dele (e, se escolhido, como o Início dela). */
    protected function afterCreate(): void
    {
        if ($this->record->tenant_id) {
            DashboardTemplateService::atribuir($this->record, [$this->record->tenant_id], $this->comoInicio);
        }
    }
}
