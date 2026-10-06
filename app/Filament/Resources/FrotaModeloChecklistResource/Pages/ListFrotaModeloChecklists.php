<?php

namespace App\Filament\Resources\FrotaModeloChecklistResource\Pages;

use App\Filament\Resources\FrotaModeloChecklistResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFrotaModeloChecklists extends ListRecords
{
    protected static string $resource = FrotaModeloChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('Novo modelo')];
    }
}
