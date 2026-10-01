<?php

namespace App\Filament\Resources\SpecializedServiceResource\Pages;

use App\Filament\Resources\SpecializedServiceResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageSpecializedServices extends ManageRecords
{
    protected static string $resource = SpecializedServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
