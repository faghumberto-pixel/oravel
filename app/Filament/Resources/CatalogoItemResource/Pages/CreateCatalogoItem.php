<?php

namespace App\Filament\Resources\CatalogoItemResource\Pages;

use App\Filament\Resources\CatalogoItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCatalogoItem extends CreateRecord
{
    protected static string $resource = CatalogoItemResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['origem' => 'tenant', 'personalizado' => true];
    }
}
