<?php

namespace App\Filament\Resources\FreightRecordResource\Pages;

use App\Filament\Resources\FreightRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFreightRecords extends ListRecords
{
    protected static string $resource = FreightRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
