<?php

namespace App\Filament\Resources\LocationResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\LocationResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('locations')]
class CreateLocation extends CreateRecord
{
    protected static string $resource = LocationResource::class;
}
