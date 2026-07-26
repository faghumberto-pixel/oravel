<?php

namespace App\Filament\Resources\PreventiveMaintenanceExecutionResource\Pages;

use App\Filament\Resources\PreventiveMaintenanceExecutionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPreventiveMaintenanceExecution extends EditRecord
{
    protected static string $resource = PreventiveMaintenanceExecutionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
