<?php

namespace App\Filament\Resources\FrotaBateriaResource\Pages;

use App\Filament\Resources\FrotaBateriaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFrotaBaterias extends ListRecords
{
    protected static string $resource = FrotaBateriaResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Nova bateria')];
    }
}
