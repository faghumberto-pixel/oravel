<?php

namespace App\Filament\Resources\AbcMatrixResource\Pages;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\AbcMatrixResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

#[BelongsToFeature('maintenance_matrix')]
class ListAbcMatrices extends ListRecords
{
    protected static string $resource = AbcMatrixResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
