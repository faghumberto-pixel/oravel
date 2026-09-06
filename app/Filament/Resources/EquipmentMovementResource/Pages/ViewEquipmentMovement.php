<?php

namespace App\Filament\Resources\EquipmentMovementResource\Pages;

use App\Filament\Concerns\HasRecordPrintAction;
use App\Filament\Resources\EquipmentMovementResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEquipmentMovement extends ViewRecord
{
    use HasRecordPrintAction;

    protected static string $resource = EquipmentMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->printAction()];
    }
}
