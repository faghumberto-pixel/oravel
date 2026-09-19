<?php

namespace App\Filament\Central\Resources\DatabaseBackupResource\Pages;

use App\Filament\Central\Resources\DatabaseBackupResource;
use Filament\Resources\Pages\ListRecords;

class ListDatabaseBackups extends ListRecords
{
    protected static string $resource = DatabaseBackupResource::class;
}
