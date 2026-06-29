<?php

namespace App\Filament\Resources\ChecklistGroupResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\ChecklistGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

#[BelongsToFeature('checklists')]
class EditChecklistGroup extends EditRecord
{
    protected static string $resource = ChecklistGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
