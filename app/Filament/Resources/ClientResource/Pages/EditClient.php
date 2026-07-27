<?php

namespace App\Filament\Resources\ClientResource\Pages;

use App\Filament\Pages\CaixaDeEmail;
use App\Filament\Resources\ClientResource;
use App\Models\Client;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('enviar_email')
                ->label('Enviar E-mail')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->visible(fn (Client $record) => filled($record->email))
                ->url(fn (Client $record) => CaixaDeEmail::getUrl([
                    'to' => $record->email,
                    'related_type' => Client::class,
                    'related_id' => $record->id,
                ])),

            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Redireciona de volta para a lista após editar, garantindo o contexto do Tenant.
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
