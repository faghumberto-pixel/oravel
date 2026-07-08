<?php

namespace App\Filament\Resources\FleetVehicleResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Resources\FleetVehicleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFleetVehicles extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = FleetVehicleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            $this->printAction(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FleetVehicleResource\Widgets\FleetVehicleStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 5;
    }
}
