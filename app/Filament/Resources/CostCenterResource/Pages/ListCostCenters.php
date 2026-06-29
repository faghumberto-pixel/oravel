<?php

namespace App\Filament\Resources\CostCenterResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\CostCenterResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

#[BelongsToFeature('cost_centers')]
class ListCostCenters extends ListRecords
{
    protected static string $resource = CostCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
