<?php

namespace App\Filament\Resources\MaterialCategoryResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\MaterialCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('materials')]
class CreateMaterialCategory extends CreateRecord
{
    protected static string $resource = MaterialCategoryResource::class;
}
