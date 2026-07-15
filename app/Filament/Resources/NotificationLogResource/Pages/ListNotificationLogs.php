<?php

namespace App\Filament\Resources\NotificationLogResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Exports\NotificationLogExporter;
use App\Filament\Resources\NotificationLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNotificationLogs extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = NotificationLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->printAction(),
            Actions\ExportAction::make()->exporter(NotificationLogExporter::class),
        ];
    }
}
