<?php

namespace App\Filament\Central\Resources\LandingPageLeadResource\Pages;

use App\Filament\Central\Resources\LandingPageLeadResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageLandingPageLeads extends ManageRecords
{
    protected static string $resource = LandingPageLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
