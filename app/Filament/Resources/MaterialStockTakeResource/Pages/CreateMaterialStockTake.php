<?php

namespace App\Filament\Resources\MaterialStockTakeResource\Pages;

use App\Filament\Resources\MaterialStockTakeResource;
use App\Support\Tenancy;
use Filament\Resources\Pages\CreateRecord;

class CreateMaterialStockTake extends CreateRecord
{
    protected static string $resource = MaterialStockTakeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Tenancy::current()?->id;

        return $data;
    }

    /**
     * Uma linha por Material do tenant, com o saldo do sistema no
     * momento em que o inventario comecou -- ver
     * MaterialStockTake::populateFromMaterials().
     */
    protected function afterCreate(): void
    {
        $this->record->populateFromMaterials();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
