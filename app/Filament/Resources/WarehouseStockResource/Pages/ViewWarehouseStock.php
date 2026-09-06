<?php

namespace App\Filament\Resources\WarehouseStockResource\Pages;

use App\Filament\Concerns\HasRecordPrintAction;
use App\Filament\Resources\WarehouseStockResource;
use Filament\Resources\Pages\ViewRecord;

class ViewWarehouseStock extends ViewRecord
{
    use HasRecordPrintAction;

    protected static string $resource = WarehouseStockResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->printAction()];
    }
}
