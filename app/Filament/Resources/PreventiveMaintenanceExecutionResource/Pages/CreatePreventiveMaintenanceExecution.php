<?php

namespace App\Filament\Resources\PreventiveMaintenanceExecutionResource\Pages;

use App\Filament\Resources\PreventiveMaintenanceExecutionResource;
use App\Support\Tenancy;
use Filament\Resources\Pages\CreateRecord;

class CreatePreventiveMaintenanceExecution extends CreateRecord
{
    protected static string $resource = PreventiveMaintenanceExecutionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Tenancy::current()?->id;
        $data['technician_id'] = $data['technician_id'] ?? auth()->id();

        return $data;
    }
}
