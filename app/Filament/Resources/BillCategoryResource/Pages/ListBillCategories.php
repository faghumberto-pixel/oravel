<?php

namespace App\Filament\Resources\BillCategoryResource\Pages;

use App\Filament\Resources\BillCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

use AppFilamentAttributesBelongsToFeature;
#[BelongsToFeature('bill_categories')]
class ListBillCategories extends ListRecords
{
    protected static string $resource = BillCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Criar Categoria de Conta'),
        ];
    }
}