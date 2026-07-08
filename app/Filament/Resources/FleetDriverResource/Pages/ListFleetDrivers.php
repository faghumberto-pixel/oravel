<?php

namespace App\Filament\Resources\FleetDriverResource\Pages;

use App\Filament\Resources\FleetDriverResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFleetDrivers extends ListRecords
{
    protected static string $resource = FleetDriverResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FleetDriverResource\Widgets\FleetDriverStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
