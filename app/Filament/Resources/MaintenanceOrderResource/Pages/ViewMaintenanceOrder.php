<?php

namespace App\Filament\Resources\MaintenanceOrderResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\MaintenanceOrderResource;
use Filament\Resources\Pages\ViewRecord;

#[BelongsToFeature('maintenance')]
class ViewMaintenanceOrder extends ViewRecord
{
    protected static string $resource = MaintenanceOrderResource::class;
}
