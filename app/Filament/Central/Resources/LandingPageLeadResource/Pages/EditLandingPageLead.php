<?php

namespace App\Filament\Central\Resources\LandingPageLeadResource\Pages;

use App\Filament\Central\Resources\LandingPageLeadResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLandingPageLead extends EditRecord
{
    protected static string $resource = LandingPageLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
