<?php

namespace App\Filament\Resources\BillCategoryResource\Pages;

use App\Filament\Resources\BillCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

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