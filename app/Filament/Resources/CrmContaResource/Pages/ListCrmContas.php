<?php

namespace App\Filament\Resources\CrmContaResource\Pages;

use App\Filament\Resources\CrmContaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCrmContas extends ListRecords
{
    protected static string $resource = CrmContaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
