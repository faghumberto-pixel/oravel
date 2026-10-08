<?php

namespace App\Filament\Resources\PropostaComercialResource\Pages;

use App\Filament\Resources\PropostaComercialResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

/**
 * Edição do RASCUNHO pelo computador (cliente, itens, termos). Depois de enviada ao Comercial a proposta fica
 * travada: PropostaComercialResource::canEdit() só libera enquanto o status é "rascunho".
 */
class EditPropostaComercial extends EditRecord
{
    protected static string $resource = PropostaComercialResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction(),
            Actions\Action::make('salvar_e_enviar')
                ->label('Salvar e enviar ao Comercial')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Salvar e enviar ao Comercial?')
                ->modalDescription('O Comercial recebe um aviso por e-mail para revisar. Depois de enviada, a proposta não pode mais ser editada.')
                ->action(function () {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

                    try {
                        $this->getRecord()->refresh()->enviarParaComercial();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Salvo, mas não foi possível enviar')->body($e->getMessage())->warning()->send();

                        return;
                    }

                    Notification::make()->title('Proposta enviada ao Comercial')->success()->send();
                    $this->redirect($this->getRedirectUrl());
                }),
            $this->getCancelFormAction(),
        ];
    }
}
