<?php

namespace App\Filament\Resources\PropostaComercialResource\Pages;

use App\Models\PropostaComercial;
use Filament\Forms;
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
                ->label('Salvar e enviar para revisão')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->modalHeading('Salvar e enviar para revisão?')
                ->modalDescription('Escolha quem vai revisar. Depois de enviada, a proposta não pode mais ser editada.')
                ->form([
                    Forms\Components\Select::make('destinatario')
                        ->label('Enviar para')
                        ->options(fn () => PropostaComercial::opcoesDestinatarios())
                        ->default(fn () => PropostaComercial::destinatarioPadrao())
                        ->searchable()
                        ->required()
                        ->helperText('A pessoa escolhida recebe o aviso para revisar a proposta.'),
                ])
                ->action(function (array $data) {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

                    try {
                        $this->getRecord()->refresh()->enviarParaComercial($data['destinatario'] ?? null);
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Salvo, mas não foi possível enviar')->body($e->getMessage())->warning()->send();

                        return;
                    }

                    Notification::make()->title('Proposta enviada para revisão')->success()->send();
                    $this->redirect($this->getRedirectUrl());
                }),
            $this->getCancelFormAction(),
        ];
    }
}
