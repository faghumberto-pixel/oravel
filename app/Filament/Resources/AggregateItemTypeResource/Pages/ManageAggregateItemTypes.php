<?php

namespace App\Filament\Resources\AggregateItemTypeResource\Pages;

use App\Filament\Resources\AggregateItemTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAggregateItemTypes extends ManageRecords
{
    protected static string $resource = AggregateItemTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
