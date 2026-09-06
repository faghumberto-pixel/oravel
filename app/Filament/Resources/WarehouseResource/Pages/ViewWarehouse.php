<?php

namespace App\Filament\Resources\WarehouseResource\Pages;

use App\Filament\Concerns\HasRecordPrintAction;
use App\Filament\Resources\WarehouseResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewWarehouse extends ViewRecord
{
    use HasRecordPrintAction;

    protected static string $resource = WarehouseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->printAction(),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
