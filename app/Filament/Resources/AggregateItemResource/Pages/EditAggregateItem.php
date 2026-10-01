<?php

namespace App\Filament\Resources\AggregateItemResource\Pages;

use App\Filament\Resources\AggregateItemResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAggregateItem extends EditRecord
{
    protected static string $resource = AggregateItemResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
