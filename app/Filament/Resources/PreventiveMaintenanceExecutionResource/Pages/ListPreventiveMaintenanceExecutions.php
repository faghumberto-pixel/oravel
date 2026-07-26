<?php

namespace App\Filament\Resources\PreventiveMaintenanceExecutionResource\Pages;

use App\Filament\Resources\PreventiveMaintenanceExecutionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPreventiveMaintenanceExecutions extends ListRecords
{
    protected static string $resource = PreventiveMaintenanceExecutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
