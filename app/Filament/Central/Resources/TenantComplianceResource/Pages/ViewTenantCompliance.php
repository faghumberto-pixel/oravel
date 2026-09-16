<?php

namespace App\Filament\Central\Resources\TenantComplianceResource\Pages;

use App\Filament\Central\Resources\TenantComplianceResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTenantCompliance extends ViewRecord
{
    protected static string $resource = TenantComplianceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->hidden(),
            Actions\DeleteAction::make()->hidden(),
        ];
    }

    protected function getContentTabLabel(): ?string
    {
        return 'Visão Geral';
    }
}
