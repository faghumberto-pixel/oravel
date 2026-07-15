<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\ActivityLogExporter;
use App\Filament\Resources\ActivityLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogEntries extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->printAction(),
            Actions\ExportAction::make()->exporter(ActivityLogExporter::class),
        ];
    }
}
