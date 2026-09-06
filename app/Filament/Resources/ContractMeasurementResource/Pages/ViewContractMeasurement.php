<?php

namespace App\Filament\Resources\ContractMeasurementResource\Pages;

use App\Filament\Concerns\HasRecordPrintAction;
use App\Filament\Resources\ContractMeasurementResource;
use Filament\Resources\Pages\ViewRecord;

class ViewContractMeasurement extends ViewRecord
{
    use HasRecordPrintAction;

    protected static string $resource = ContractMeasurementResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->printAction()];
    }
}
