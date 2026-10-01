<?php

namespace App\Filament\Resources\AggregateItemExitResource\Pages;

use App\Filament\Resources\AggregateItemExitResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAggregateItemExits extends ManageRecords
{
    protected static string $resource = AggregateItemExitResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
