<?php

namespace App\Filament\Central\Resources\SignatureResource\Pages;

use App\Filament\Central\Resources\SignatureResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewSignature extends ViewRecord
{
    protected static string $resource = SignatureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
