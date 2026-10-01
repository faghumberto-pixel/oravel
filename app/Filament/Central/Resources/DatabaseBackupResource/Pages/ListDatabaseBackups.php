<?php

namespace App\Filament\Central\Resources\DatabaseBackupResource\Pages;

use App\Filament\Central\Resources\DatabaseBackupResource;
use App\Filament\Central\Widgets\BackupStatsOverview;
use Filament\Resources\Pages\ListRecords;

class ListDatabaseBackups extends ListRecords
{
    protected static string $resource = DatabaseBackupResource::class;

    protected function getHeaderWidgets(): array
    {
        return [BackupStatsOverview::class];
    }
}
