<?php

namespace App\Filament\Resources\FleetMaintenancePlanResource\Pages;

use App\Filament\Resources\FleetMaintenancePlanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFleetMaintenancePlans extends ListRecords
{
    protected static string $resource = FleetMaintenancePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FleetMaintenancePlanResource\Widgets\FleetMaintenancePlanStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
