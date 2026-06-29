<?php

namespace App\Filament\Resources\FleetStatusResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\FleetStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

#[BelongsToFeature('fleet')]
class ListFleetStatuses extends ListRecords
{
    protected static string $resource = FleetStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
