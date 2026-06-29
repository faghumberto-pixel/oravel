<?php

namespace App\Filament\Resources\ChecklistTemplateResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\ChecklistTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('checklists')]
class CreateChecklistTemplate extends CreateRecord
{
    protected static string $resource = ChecklistTemplateResource::class;
}
