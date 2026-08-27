<?php

namespace App\Filament\Central\Resources\PmpEquipmentFamilyResource\Pages;

use App\Filament\Central\Resources\PmpEquipmentFamilyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPmpEquipmentFamily extends EditRecord
{
    protected static string $resource = PmpEquipmentFamilyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
