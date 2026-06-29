<?php

namespace App\Filament\Resources\ContractResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\ContractResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('contracts')]
class CreateContract extends CreateRecord
{
    protected static string $resource = ContractResource::class;
}
