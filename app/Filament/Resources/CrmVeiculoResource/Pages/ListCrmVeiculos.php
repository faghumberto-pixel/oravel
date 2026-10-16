<?php

namespace App\Filament\Resources\CrmVeiculoResource\Pages;

use App\Filament\Resources\CrmVeiculoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCrmVeiculos extends ListRecords
{
    protected static string $resource = CrmVeiculoResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
