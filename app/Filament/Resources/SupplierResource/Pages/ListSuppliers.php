<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

#[BelongsToFeature('suppliers')]
class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    protected static ?string $title = 'Fornecedores';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
