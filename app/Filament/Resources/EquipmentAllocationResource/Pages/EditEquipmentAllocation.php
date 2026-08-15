<?php

namespace App\Filament\Resources\EquipmentAllocationResource\Pages;

use App\Filament\Resources\EquipmentAllocationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEquipmentAllocation extends EditRecord
{
    protected static string $resource = EquipmentAllocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
