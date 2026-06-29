<?php

namespace App\Filament\Resources\ChecklistTemplateResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\ChecklistTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

#[BelongsToFeature('checklists')]
class EditChecklistTemplate extends EditRecord
{
    protected static string $resource = ChecklistTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
