<?php

namespace App\Filament\Resources\FrotaBateriaResource\Pages;

use App\Filament\Resources\FrotaBateriaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFrotaBateria extends CreateRecord
{
    protected static string $resource = FrotaBateriaResource::class;

    /** Cadastro com item e almoxarifado escolhidos: entra 1 unidade no estoque. */
    protected function afterCreate(): void
    {
        try {
            app(\App\Services\Frota\EstoqueFrotaService::class)->entrada($this->record, 1, 'entry_purchase', 'Bateria cadastrado(a) na frota');
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Filament\Notifications\Notification::make()->title(collect($e->errors())->flatten()->first())->danger()->send();
        }
    }
}
