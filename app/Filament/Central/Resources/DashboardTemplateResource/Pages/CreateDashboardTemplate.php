<?php

namespace App\Filament\Central\Resources\DashboardTemplateResource\Pages;

use App\Dashboards\Personalizacao\DashboardTemplateService;
use App\Filament\Central\Resources\DashboardTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDashboardTemplate extends CreateRecord
{
    protected static string $resource = DashboardTemplateResource::class;

    /** Dashboard exclusivo já nasce atribuído à empresa dele. */
    protected function afterCreate(): void
    {
        if ($this->record->tenant_id) {
            DashboardTemplateService::atribuir($this->record, [$this->record->tenant_id]);
        }
    }
}
