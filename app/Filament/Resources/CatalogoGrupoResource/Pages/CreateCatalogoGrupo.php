<?php

namespace App\Filament\Resources\CatalogoGrupoResource\Pages;

use App\Filament\Resources\CatalogoGrupoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCatalogoGrupo extends CreateRecord
{
    protected static string $resource = CatalogoGrupoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // grupo criado do zero pelo tenant: origem tenant, sem template
        return $data + ['origem' => 'tenant', 'personalizado' => true, 'is_active' => true];
    }
}
