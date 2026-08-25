<?php

namespace App\Filament\Resources\PropostaComercialTemplateResource\Pages;

use App\Filament\Resources\PropostaComercialTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPropostaComercialTemplate extends EditRecord
{
    protected static string $resource = PropostaComercialTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
