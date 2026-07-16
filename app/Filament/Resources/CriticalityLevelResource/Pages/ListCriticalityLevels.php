<?php

namespace App\Filament\Resources\CriticalityLevelResource\Pages;

use App\Filament\Resources\CriticalityLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCriticalityLevels extends ListRecords
{
    protected static string $resource = CriticalityLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
