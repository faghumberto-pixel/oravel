<?php

namespace App\Filament\Resources\MaintenancePlanResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\MaintenancePlanResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('maintenance')]
class CreateMaintenancePlan extends CreateRecord
{
    protected static string $resource = MaintenancePlanResource::class;
}
