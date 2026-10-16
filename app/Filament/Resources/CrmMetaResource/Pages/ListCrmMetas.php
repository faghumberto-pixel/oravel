<?php

namespace App\Filament\Resources\CrmMetaResource\Pages;

use App\Filament\Resources\CrmMetaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCrmMetas extends ListRecords
{
    protected static string $resource = CrmMetaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
