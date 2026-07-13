<?php

namespace App\Filament\Resources\MaintenanceStatusHistoryResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Resources\MaintenanceStatusHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceStatusHistories extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = MaintenanceStatusHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->printAction(),
        ];
    }
}
