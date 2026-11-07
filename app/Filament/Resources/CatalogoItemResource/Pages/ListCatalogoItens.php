<?php

namespace App\Filament\Resources\CatalogoItemResource\Pages;

use App\Filament\Resources\CatalogoItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCatalogoItens extends ListRecords
{
    protected static string $resource = CatalogoItemResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Novo item')];
    }
}
