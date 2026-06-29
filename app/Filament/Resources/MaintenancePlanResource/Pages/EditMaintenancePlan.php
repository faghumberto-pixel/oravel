<?php

namespace App\Filament\Resources\MaintenancePlanResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\MaintenancePlanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

#[BelongsToFeature('maintenance')]
class EditMaintenancePlan extends EditRecord
{
    protected static string $resource = MaintenancePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
