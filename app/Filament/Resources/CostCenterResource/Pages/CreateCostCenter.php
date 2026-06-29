<?php

namespace App\Filament\Resources\CostCenterResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\CostCenterResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('cost_centers')]
class CreateCostCenter extends CreateRecord
{
    protected static string $resource = CostCenterResource::class;
}
