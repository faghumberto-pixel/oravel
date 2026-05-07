<?php

namespace App\Filament\Resources\InternalUnitResource\Pages;

use App\Filament\Resources\InternalUnitResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInternalUnits extends ListRecords
{
    protected static string $resource = InternalUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
