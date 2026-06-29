<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\SupplierResource;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('suppliers')]
class CreateSupplier extends CreateRecord
{
    protected static string $resource = SupplierResource::class;
}
