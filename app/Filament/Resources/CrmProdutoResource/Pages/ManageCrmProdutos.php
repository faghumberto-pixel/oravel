<?php

namespace App\Filament\Resources\CrmProdutoResource\Pages;

use App\Filament\Resources\CrmProdutoResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageCrmProdutos extends ManageRecords
{
    protected static string $resource = CrmProdutoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
