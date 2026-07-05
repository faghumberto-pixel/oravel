<?php

namespace App\Filament\Resources\FleetMaintenancePlanResource\Pages;

use App\Filament\Resources\FleetMaintenancePlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditFleetMaintenancePlan extends EditRecord
{
    protected static string $resource = FleetMaintenancePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
