<?php

namespace App\Filament\Resources\CostCenterResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\CostCenterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

#[BelongsToFeature('cost_centers')]
class EditCostCenter extends EditRecord
{
    protected static string $resource = CostCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
