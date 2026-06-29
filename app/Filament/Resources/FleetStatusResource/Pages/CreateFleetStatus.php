<?php

namespace App\Filament\Resources\FleetStatusResource\Pages;

use App\Filament\Resources\FleetStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

use AppFilamentAttributesBelongsToFeature;
#[BelongsToFeature('fleet')]
class CreateFleetStatus extends CreateRecord
{
    protected static string $resource = FleetStatusResource::class;
}
