<?php

namespace App\Filament\Resources\SolicitacaoLocacaoResource\Pages;

use App\Filament\Resources\SolicitacaoLocacaoResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSolicitacoesLocacao extends ListRecords
{
    protected static string $resource = SolicitacaoLocacaoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}