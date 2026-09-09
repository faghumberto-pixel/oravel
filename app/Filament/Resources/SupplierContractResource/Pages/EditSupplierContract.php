<?php

namespace App\Filament\Resources\SupplierContractResource\Pages;

use App\Filament\Resources\SupplierContractResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupplierContract extends EditRecord
{
    protected static string $resource = SupplierContractResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
