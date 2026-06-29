<?php

namespace App\Filament\Resources\ChecklistGroupResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\ChecklistGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('checklists')]
class CreateChecklistGroup extends CreateRecord
{
    protected static string $resource = ChecklistGroupResource::class;
}
