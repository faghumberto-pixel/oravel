<?php

namespace App\Filament\Resources\FrotaSinistroResource\Pages;

use App\Filament\Resources\FrotaSinistroResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFrotaSinistros extends ListRecords
{
    protected static string $resource = FrotaSinistroResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Novo sinistro')];
    }
}
