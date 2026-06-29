<?php

namespace App\Filament\Resources\MaterialResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\MaterialResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('materials')]
class CreateMaterial extends CreateRecord
{
    protected static string $resource = MaterialResource::class;
}
