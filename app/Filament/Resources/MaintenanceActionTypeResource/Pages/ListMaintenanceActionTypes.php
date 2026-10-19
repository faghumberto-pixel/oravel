<?php

namespace App\Filament\Resources\MaintenanceActionTypeResource\Pages;

use App\Filament\Resources\MaintenanceActionTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceActionTypes extends ListRecords
{
    protected static string $resource = MaintenanceActionTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Novo tipo de ação')];
    }
}
