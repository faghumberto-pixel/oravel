<?php

namespace App\Filament\Resources\PartsRequestResource\Pages;

use App\Filament\Resources\PartsRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePartsRequests extends ManageRecords
{
    protected static string $resource = PartsRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Removido o CreateAction para que as peças venham apenas das OS
        ];
    }
}