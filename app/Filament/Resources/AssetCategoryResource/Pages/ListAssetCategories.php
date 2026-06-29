<?php

namespace App\Filament\Resources\AssetCategoryResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\AssetCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

#[BelongsToFeature('assets')]
class ListAssetCategories extends ListRecords
{
    protected static string $resource = AssetCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}