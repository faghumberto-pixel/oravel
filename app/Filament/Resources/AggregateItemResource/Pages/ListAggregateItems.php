<?php

namespace App\Filament\Resources\AggregateItemResource\Pages;

use App\Filament\Resources\AggregateItemResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAggregateItems extends ListRecords
{
    protected static string $resource = AggregateItemResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
