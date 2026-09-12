<?php

namespace App\Filament\Resources\LandingPageLeadResource\Pages;

use App\Filament\Resources\LandingPageLeadResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLandingPageLeads extends ListRecords
{
    protected static string $resource = LandingPageLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
