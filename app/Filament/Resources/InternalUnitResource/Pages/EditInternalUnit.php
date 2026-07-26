<?php

namespace App\Filament\Resources\InternalUnitResource\Pages;

use App\Filament\Resources\InternalUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInternalUnit extends EditRecord
{
    protected static string $resource = InternalUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
