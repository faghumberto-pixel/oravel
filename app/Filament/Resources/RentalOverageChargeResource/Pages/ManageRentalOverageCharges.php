<?php

namespace App\Filament\Resources\RentalOverageChargeResource\Pages;

use App\Filament\Resources\RentalOverageChargeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageRentalOverageCharges extends ManageRecords
{
    protected static string $resource = RentalOverageChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
