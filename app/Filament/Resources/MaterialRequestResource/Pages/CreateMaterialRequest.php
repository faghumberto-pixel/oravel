<?php

namespace App\Filament\Resources\MaterialRequestResource\Pages;

use App\Filament\Resources\MaterialRequestResource;
use App\Support\Tenancy;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterialRequest extends CreateRecord
{
    protected static string $resource = MaterialRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Tenancy::current()?->id;
        $data['requested_at'] = now();

        return $data;
    }
}
