<?php

namespace App\Filament\Resources\CkTemplateResource\Pages;

use App\Filament\Resources\CkTemplateResource;
use App\Models\CkTemplate;
use Filament\Resources\Pages\CreateRecord;

class CreateCkTemplate extends CreateRecord
{
    protected static string $resource = CkTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + [
            'tenant_id' => CkTemplateResource::tenantId(), 'origem' => CkTemplate::ORIGEM_CUSTOMIZADO, 'personalizado' => true, 'versao' => 1,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
