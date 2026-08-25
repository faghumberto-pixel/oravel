<?php

namespace App\Filament\Resources\PropostaComercialTemplateResource\Pages;

use App\Filament\Resources\PropostaComercialTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPropostaComercialTemplates extends ListRecords
{
    protected static string $resource = PropostaComercialTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
