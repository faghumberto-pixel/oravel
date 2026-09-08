<?php

namespace App\Filament\Resources\SupplierContractResource\Pages;

use App\Filament\Resources\SupplierContractResource;
use App\Support\Tenancy;
use Filament\Resources\Pages\CreateRecord;

class CreateSupplierContract extends CreateRecord
{
    protected static string $resource = SupplierContractResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Tenancy::current()?->id;

        return $data;
    }
}
