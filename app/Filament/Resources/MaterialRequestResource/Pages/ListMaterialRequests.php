<?php

namespace App\Filament\Resources\MaterialRequestResource\Pages;

use App\Filament\Exports\MaterialRequestExporter;
use App\Filament\Resources\MaterialRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaterialRequests extends ListRecords
{
    protected static string $resource = MaterialRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Nova Requisição'),
            Actions\ExportAction::make()->exporter(MaterialRequestExporter::class),
        ];
    }
}
