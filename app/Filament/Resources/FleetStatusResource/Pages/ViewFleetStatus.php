<?php

namespace App\Filament\Resources\FleetStatusResource\Pages;

use App\Filament\Resources\FleetStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFleetStatus extends ViewRecord
{
    protected static string $resource = FleetStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
