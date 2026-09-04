<?php

namespace App\Filament\Resources\MaterialLocationStockResource\Pages;

use App\Filament\Resources\MaterialLocationStockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterialLocationStocks extends ListRecords
{
    protected static string $resource = MaterialLocationStockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
