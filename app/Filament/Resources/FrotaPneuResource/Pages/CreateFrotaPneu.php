<?php

namespace App\Filament\Resources\FrotaPneuResource\Pages;

use App\Filament\Resources\FrotaPneuResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFrotaPneu extends CreateRecord
{
    protected static string $resource = FrotaPneuResource::class;

    /** Cadastro com item e almoxarifado escolhidos: entra 1 unidade no estoque. */
    protected function afterCreate(): void
    {
        try {
            app(\App\Services\Frota\EstoqueFrotaService::class)->entrada($this->record, 1, 'entry_purchase', 'Pneu cadastrado(a) na frota');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Filament\Notifications\Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }
}
