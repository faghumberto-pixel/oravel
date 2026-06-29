<?php

namespace App\Filament\Resources\MaterialCategoryResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\MaterialCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

#[BelongsToFeature('materials')]
class EditMaterialCategory extends EditRecord
{
    protected static string $resource = MaterialCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
