<?php

namespace App\Filament\Central\Resources\ProspectResource\Pages;

use App\Filament\Central\Resources\ProspectResource;
use App\Filament\Central\Resources\ProspectResource\RegistrarEntrevistaAction;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProspect extends EditRecord
{
    protected static string $resource = ProspectResource::class;

    protected function getHeaderActions(): array
    {
        return [RegistrarEntrevistaAction::make(), Actions\DeleteAction::make()];
    }
}
