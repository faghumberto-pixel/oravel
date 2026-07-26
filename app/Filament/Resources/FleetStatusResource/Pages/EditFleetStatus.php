<?php

namespace App\Filament\Resources\FleetStatusResource\Pages;

use App\Filament\Resources\FleetStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFleetStatus extends EditRecord
{
    protected static string $resource = FleetStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
