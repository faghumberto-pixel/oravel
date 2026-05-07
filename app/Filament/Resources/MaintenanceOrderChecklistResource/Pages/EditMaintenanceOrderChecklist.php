<?php

namespace App\Filament\Resources\MaintenanceOrderChecklistResource\Pages;

use App\Filament\Resources\MaintenanceOrderChecklistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceOrderChecklist extends EditRecord
{
    protected static string $resource = MaintenanceOrderChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
