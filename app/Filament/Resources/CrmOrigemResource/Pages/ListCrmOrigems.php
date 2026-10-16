<?php

namespace App\Filament\Resources\CrmOrigemResource\Pages;

use App\Filament\Resources\CrmOrigemResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ListCrmOrigems extends ManageRecords
{
    protected static string $resource = CrmOrigemResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
