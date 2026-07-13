<?php

namespace App\Filament\Resources\NotificationLogResource\Pages;

use App\Filament\Concerns\HasPrintAction;
use App\Filament\Resources\NotificationLogResource;
use Filament\Resources\Pages\ListRecords;

class ListNotificationLogs extends ListRecords
{
    use HasPrintAction;

    protected static string $resource = NotificationLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->printAction(),
        ];
    }
}
