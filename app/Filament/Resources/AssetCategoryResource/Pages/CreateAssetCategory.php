<?php

namespace App\Filament\Resources\AssetCategoryResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\AssetCategoryResource;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('assets')]
class CreateAssetCategory extends CreateRecord
{
    protected static string $resource = AssetCategoryResource::class;
}