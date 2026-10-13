<?php

namespace App\Filament\Resources\FrotaPercursoResource\Pages;

use App\Filament\Resources\FrotaPercursoResource;
use Filament\Resources\Pages\ListRecords;

class ListFrotaPercursos extends ListRecords
{
    protected static string $resource = FrotaPercursoResource::class;

    protected function getHeaderActions(): array
    {
        return [\Filament\Actions\CreateAction::make()->label('Novo percurso')];
    }
}
