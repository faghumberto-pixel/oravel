<?php

namespace App\Filament\Resources\Nr13InspectionPeriodicityResource\Pages;

use App\Filament\Resources\Nr13InspectionPeriodicityResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageNr13InspectionPeriodicities extends ManageRecords
{
    protected static string $resource = Nr13InspectionPeriodicityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
