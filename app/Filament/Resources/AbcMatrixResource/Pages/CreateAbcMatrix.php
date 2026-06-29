<?php

namespace App\Filament\Resources\AbcMatrixResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\AbcMatrixResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

#[BelongsToFeature('maintenance_matrix')]
class CreateAbcMatrix extends CreateRecord
{
    protected static string $resource = AbcMatrixResource::class;
}
