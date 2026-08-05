<?php

namespace App\Filament\Central\Resources\SalesLeadResource\Pages;

use App\Filament\Central\Resources\SalesLeadResource;
use App\Filament\Exports\SalesLeadExporter;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalesLeads extends ListRecords
{
    protected static string $resource = SalesLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()->exporter(SalesLeadExporter::class),
            Actions\CreateAction::make(),
        ];
    }
}
