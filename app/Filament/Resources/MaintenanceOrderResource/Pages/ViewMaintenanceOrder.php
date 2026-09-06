<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Pages;

use App\Filament\Concerns\HasRecordPrintAction;
use App\Filament\Resources\MaintenanceOrderResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMaintenanceOrder extends ViewRecord
{
    use HasRecordPrintAction;

    protected static string $resource = MaintenanceOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->printAction()];
    }
}
