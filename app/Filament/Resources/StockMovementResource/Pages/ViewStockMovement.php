<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Filament\Concerns\HasRecordPrintAction;
use App\Filament\Resources\StockMovementResource;
use Filament\Resources\Pages\ViewRecord;

class ViewStockMovement extends ViewRecord
{
    use HasRecordPrintAction;

    protected static string $resource = StockMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->printAction()];
    }
}
