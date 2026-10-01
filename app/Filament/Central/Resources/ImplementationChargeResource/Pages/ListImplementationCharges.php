<?php

namespace App\Filament\Central\Resources\ImplementationChargeResource\Pages;

use App\Filament\Central\Resources\ImplementationChargeResource;
use App\Filament\Central\Widgets\ImplementationStats;
use Filament\Resources\Pages\ListRecords;

class ListImplementationCharges extends ListRecords
{
    protected static string $resource = ImplementationChargeResource::class;

    protected function getHeaderWidgets(): array
    {
        return [ImplementationStats::class];
    }
}
