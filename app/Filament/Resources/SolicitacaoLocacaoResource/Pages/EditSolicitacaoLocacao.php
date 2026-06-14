<?php

namespace App\Filament\Resources\SolicitacaoLocacaoResource\Pages;

use App\Filament\Resources\SolicitacaoLocacaoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSolicitacaoLocacao extends EditRecord
{
    protected static string $resource = SolicitacaoLocacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}