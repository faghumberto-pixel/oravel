<?php

namespace App\Filament\Resources\PriorityLevelResource\Pages;

use App\Filament\Resources\PriorityLevelResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePriorityLevel extends CreateRecord
{
    protected static string $resource = PriorityLevelResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['is_system' => false];
    }
}
