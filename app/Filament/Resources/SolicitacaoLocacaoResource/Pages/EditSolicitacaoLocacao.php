<?php

namespace App\Filament\Resources\SolicitacaoLocacaoResource\Pages;

use App\Filament\Attributes\BelongsToFeature;

use App\Filament\Resources\SolicitacaoLocacaoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

#[BelongsToFeature('rental_requests')]
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