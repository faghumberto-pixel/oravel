<?php

namespace App\Filament\Resources\FreightCarrierResource\Pages;

use App\Filament\Resources\FreightCarrierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFreightCarrier extends EditRecord
{
    protected static string $resource = FreightCarrierResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
