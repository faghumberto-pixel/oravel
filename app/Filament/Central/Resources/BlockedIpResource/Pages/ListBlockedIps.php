<?php

namespace App\Filament\Central\Resources\BlockedIpResource\Pages;

use App\Filament\Central\Resources\BlockedIpResource;
use Filament\Resources\Pages\ListRecords;

class ListBlockedIps extends ListRecords
{
    protected static string $resource = BlockedIpResource::class;
}
