<?php

namespace App\Filament\Resources\PmocResource\Pages;

use App\Filament\Resources\PmocResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPmocs extends ListRecords
{
    protected static string $resource = PmocResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
