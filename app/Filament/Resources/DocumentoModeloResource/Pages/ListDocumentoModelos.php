<?php

namespace App\Filament\Resources\DocumentoModeloResource\Pages;

use App\Filament\Resources\DocumentoModeloResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDocumentoModelos extends ListRecords
{
    protected static string $resource = DocumentoModeloResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
