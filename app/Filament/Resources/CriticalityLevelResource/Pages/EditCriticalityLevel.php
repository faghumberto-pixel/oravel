<?php

namespace App\Filament\Resources\CriticalityLevelResource\Pages;

use App\Filament\Resources\CriticalityLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCriticalityLevel extends EditRecord
{
    protected static string $resource = CriticalityLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
