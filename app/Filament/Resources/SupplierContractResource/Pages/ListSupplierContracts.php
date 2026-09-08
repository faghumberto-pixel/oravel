<?php

namespace App\Filament\Resources\SupplierContractResource\Pages;

use App\Filament\Resources\SupplierContractResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupplierContracts extends ListRecords
{
    protected static string $resource = SupplierContractResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
