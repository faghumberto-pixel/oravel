<?php

namespace App\Filament\Resources\FreightRecordResource\Pages;

use App\Filament\Resources\FreightRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFreightRecord extends EditRecord
{
    protected static string $resource = FreightRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
