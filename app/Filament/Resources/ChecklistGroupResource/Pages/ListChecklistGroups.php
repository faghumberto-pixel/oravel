<?php

namespace App\Filament\Resources\ChecklistGroupResource\Pages;

use App\Filament\Resources\ChecklistGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListChecklistGroups extends ListRecords
{
    protected static string $resource = ChecklistGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
