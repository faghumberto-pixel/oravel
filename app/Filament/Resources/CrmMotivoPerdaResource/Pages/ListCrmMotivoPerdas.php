<?php

namespace App\Filament\Resources\CrmMotivoPerdaResource\Pages;

use App\Filament\Resources\CrmMotivoPerdaResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ListCrmMotivoPerdas extends ManageRecords
{
    protected static string $resource = CrmMotivoPerdaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
