<?php

namespace App\Filament\Resources\InsumoTypeResource\Pages;

use App\Filament\Resources\AggregateItemTypeResource\Pages\ManageAggregateItemTypes;
use App\Filament\Resources\InsumoTypeResource;

class ManageInsumoTypes extends ManageAggregateItemTypes
{
    protected static string $resource = InsumoTypeResource::class;
}
