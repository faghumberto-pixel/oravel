<?php

namespace App\Filament\Resources\ContractMeasurementResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Resources\ContractMeasurementResource;
use Filament\Resources\Pages\ListRecords;

class ListContractMeasurements extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = ContractMeasurementResource::class;

    protected function getHeaderActions(): array
    {
        return [$this->printAction()];
    }
}
