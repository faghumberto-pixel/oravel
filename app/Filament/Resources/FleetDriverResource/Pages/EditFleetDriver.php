<?php

namespace App\Filament\Resources\FleetDriverResource\Pages;

use App\Filament\Resources\FleetDriverResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFleetDriver extends EditRecord
{
    protected static string $resource = FleetDriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
