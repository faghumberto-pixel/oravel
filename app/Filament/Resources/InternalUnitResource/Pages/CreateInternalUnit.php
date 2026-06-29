<?php

namespace App\Filament\Resources\InternalUnitResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\InternalUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('internal_units')]
class CreateInternalUnit extends CreateRecord
{
    protected static string $resource = InternalUnitResource::class;
}
