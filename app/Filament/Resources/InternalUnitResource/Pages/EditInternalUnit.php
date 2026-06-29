<?php

namespace App\Filament\Resources\InternalUnitResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\InternalUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

#[BelongsToFeature('internal_units')]
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
