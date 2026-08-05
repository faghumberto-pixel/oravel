<?php

namespace App\Filament\Resources\RentalHourFranchiseResource\Pages;

use App\Filament\Resources\RentalHourFranchiseResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageRentalHourFranchises extends ManageRecords
{
    protected static string $resource = RentalHourFranchiseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
