<?php

namespace App\Filament\Resources\PropostaComercialResource\Pages;

use App\Filament\Resources\PropostaComercialResource;
use App\Support\Tenancy;
use Filament\Resources\Pages\CreateRecord;

/**
 * Segunda porta de entrada pra criar Proposta Comercial, pelo desktop --
 * o wizard mobile (App\Livewire\PropostaComercialMobile) continua sendo o
 * caminho de campo e não muda. Nasce sempre em rascunho -- enviar pro
 * Comercial continua sendo uma ação separada na tela de detalhe
 * (ViewPropostaComercial), não algo que a criação dispara sozinha.
 */
class CreatePropostaComercial extends CreateRecord
{
    protected static string $resource = PropostaComercialResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = Tenancy::current()?->id;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
