<?php

namespace App\Filament\Central\Resources\TenantEventResource\Pages;

use App\Filament\Central\Resources\TenantEventResource;
use Filament\Resources\Pages\ListRecords;

class ListTenantEvents extends ListRecords
{
    protected static string $resource = TenantEventResource::class;
}
