<?php

namespace App\Filament\Resources\PartsRequestResource\Pages;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\PartsRequestResource;
use Filament\Resources\Pages\ManageRecords;

#[BelongsToFeature('parts_request')]
class ManagePartsRequests extends ManageRecords
{
    protected static string $resource = PartsRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Removido o CreateAction para que as peças venham apenas das OS
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            PartsRequestResource\Widgets\PartsRequestStats::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 4;
    }
}
