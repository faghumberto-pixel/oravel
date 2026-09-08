<?php

namespace App\Filament\Resources\SupplierCategoryResource\Pages;

use App\Filament\Resources\SupplierCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSupplierCategories extends ListRecords
{
    protected static string $resource = SupplierCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
