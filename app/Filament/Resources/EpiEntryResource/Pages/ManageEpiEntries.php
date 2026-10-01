<?php

namespace App\Filament\Resources\EpiEntryResource\Pages;

use App\Filament\Resources\EpiEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEpiEntries extends ManageRecords
{
    protected static string $resource = EpiEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
