<?php

namespace App\Filament\Resources\AggregateItemEntryResource\Pages;

use App\Filament\Resources\AggregateItemEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAggregateItemEntries extends ManageRecords
{
    protected static string $resource = AggregateItemEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
