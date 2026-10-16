<?php

namespace App\Filament\Resources\CrmDespesaResource\Pages;

use App\Filament\Resources\CrmDespesaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCrmDespesas extends ListRecords
{
    protected static string $resource = CrmDespesaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
