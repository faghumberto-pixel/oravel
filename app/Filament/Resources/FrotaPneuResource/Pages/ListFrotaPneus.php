<?php

namespace App\Filament\Resources\FrotaPneuResource\Pages;

use App\Filament\Resources\FrotaPneuResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFrotaPneus extends ListRecords
{
    protected static string $resource = FrotaPneuResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Novo pneu')];
    }
}
