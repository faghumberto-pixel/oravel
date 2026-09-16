<?php

namespace App\Filament\Central\Resources\TenantComplianceResource\Pages;

use App\Filament\Central\Resources\TenantComplianceResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenantCompliance extends ListRecords
{
    protected static string $resource = TenantComplianceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->hidden(),
        ];
    }
}
