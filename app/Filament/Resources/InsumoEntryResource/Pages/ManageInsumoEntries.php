<?php

namespace App\Filament\Resources\InsumoEntryResource\Pages;

use App\Filament\Resources\AggregateItemEntryResource\Pages\ManageAggregateItemEntries;
use App\Filament\Resources\InsumoEntryResource;

class ManageInsumoEntries extends ManageAggregateItemEntries
{
    protected static string $resource = InsumoEntryResource::class;
}
