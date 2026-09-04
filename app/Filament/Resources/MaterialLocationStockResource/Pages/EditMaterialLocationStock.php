<?php

namespace App\Filament\Resources\MaterialLocationStockResource\Pages;

use App\Filament\Resources\MaterialLocationStockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaterialLocationStock extends EditRecord
{
    protected static string $resource = MaterialLocationStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
