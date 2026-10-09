<?php

namespace App\Filament\Resources\PropostaComercialResource\Pages;

use App\Filament\Resources\PropostaComercialResource;
use App\Filament\Resources\SolicitacaoLocacaoResource;
use App\Models\PropostaComercial;
use App\Services\PropostaComercialAiEvaluator;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * Tela do Comercial: só visualiza (conteúdo é do vendedor, ver
 * PropostaComercialMobile) e aciona aprovar/rejeitar -- mesmo padrão de
 * EditQuote (Actions chamando os métodos do Model, engolindo
 * RuntimeException com Notification de aviso).
 */
class ViewPropostaComercial extends ViewRecord
{
    protected static string $resource = PropostaComercialResource::class;

    protected function getHeaderActions(): array
    {
        /** @var PropostaComercial $record */
        $record = $this->getRecord();

        return [
            // Enviar o rascunho ao Comercial pelo computador (antes só o app de celular fazia isso).
            Actions\Action::make('enviar_comercial')
                ->label('Enviar para revisão')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn () => $record->status === PropostaComercial::STATUS_RASCUNHO && auth()->user()?->can('update', $record))
                ->modalHeading('Enviar proposta para revisão?')
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
                ->action(function (array $data) use ($record) {
                    abort_unless(auth()->user()?->can('update', $record), 403);

                    try {
                        $record->refresh()->enviarParaComercial($data['destinatario'] ?? null);
                        $this->refreshFormData(['status', 'sent_at']);
                        Notification::make()->title('Proposta enviada para revisão')->success()->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não foi possível enviar')->body($e->getMessage())->warning()->send();
                    }
                }),

            Actions\Action::make('editar_rascunho')
                ->label('Editar')
                ->icon('heroicon-o-pencil-square')
                ->color('gray')
                ->visible(fn () => PropostaComercialResource::canEdit($record))
                ->url(fn () => PropostaComercialResource::getUrl('edit', ['record' => $record])),

            // Envio da proposta APROVADA ao cliente, pelo computador.
            Actions\Action::make('reenviar_cliente')
                ->label('Reenviar ao cliente (e-mail)')
                ->icon('heroicon-o-envelope')
                ->color('info')
                ->visible(fn () => $record->status === PropostaComercial::STATUS_APROVADA_INTERNA && auth()->user()?->can('update', $record))
                ->requiresConfirmation()
                ->modalDescription('Envia de novo o PDF e o link para o cliente aceitar ou recusar, para o e-mail cadastrado dele.')
                ->action(function () use ($record) {
                    abort_unless(auth()->user()?->can('update', $record), 403);

                    try {
                        $record->refresh()->reenviarAoCliente(auth()->user());
                        Notification::make()->title('Proposta reenviada ao cliente')->success()->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não foi possível reenviar')->body($e->getMessage())->warning()->send();
                    }
                }),

            // Pelo WhatsApp do sistema (número de quem envia): fica registrado na conversa e mostra entregue/lido.
            Actions\Action::make('whatsapp_sistema')
                ->label('Enviar pelo WhatsApp do sistema')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn () => $record->status === PropostaComercial::STATUS_APROVADA_INTERNA
                    && \App\Services\WhatsAppEmpresaService::paraUsuario(auth()->user()) !== null
                    && filled(\App\Models\TenantWhatsappSetting::withoutGlobalScopes()->where('tenant_id', $record->tenant_id)->value('template_proposta'))
                    && filled($record->client?->whatsapp ?: $record->client?->phone))
                ->requiresConfirmation()
                ->modalHeading('Enviar a proposta pelo WhatsApp?')
                ->modalDescription('O cliente recebe a mensagem com o link para ver e responder a proposta, pelo seu número de WhatsApp.')
                ->action(function () use ($record) {
                    $mensagem = $record->refresh()->enviarPorWhatsApp(auth()->user());

                    if (! $mensagem) {
                        Notification::make()->title('Não foi possível enviar')->body('Confira o WhatsApp da empresa, o modelo de proposta e o número do cliente.')->warning()->send();

                        return;
                    }

                    $mensagem->status === 'falhou'
                        ? Notification::make()->title('O WhatsApp não aceitou')->body((string) $mensagem->erro)->danger()->send()
                        : Notification::make()->title('Proposta enviada pelo WhatsApp')->success()->send();
                }),

            Actions\Action::make('whatsapp_cliente')
                ->label('Abrir no meu WhatsApp (manual)')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('success')
                ->visible(fn () => filled($record->linkWhatsapp()))
                ->url(fn () => $record->linkWhatsapp())
                ->openUrlInNewTab(),

            Actions\Action::make('aprovar')
                ->label('Aprovar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $record->status === PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL)
                ->requiresConfirmation()
                ->modalDescription('Aprovar aciona o equipamento/serviço, criando uma Solicitação de Locação vinculada (quando a proposta tiver ao menos um item de equipamento).')
                ->action(function () use ($record) {
                    try {
                        $record->aprovar(auth()->user());

                        $record->refresh();

                        if ($record->solicitacao_locacao_id) {
                            Notification::make()->title('Proposta aprovada')->body('Solicitação de Locação criada.')->success()->send();
                        } else {
                            Notification::make()
                                ->title('Proposta aprovada')
                                ->body('Proposta 100% serviço: abra a Solicitação de Locação manualmente por enquanto.')
                                ->warning()
                                ->send();
                        }
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não foi possível aprovar')->body($e->getMessage())->warning()->send();
                    }
                }),

            Actions\Action::make('rejeitar')
                ->label('Rejeitar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $record->status === PropostaComercial::STATUS_ENVIADA_PARA_COMERCIAL)
                ->requiresConfirmation()
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo da rejeição')
                        ->required(),
                ])
                ->action(function (array $data) use ($record) {
                    try {
                        $record->rejeitar(auth()->user(), $data['reason']);
                        Notification::make()->title('Proposta rejeitada')->success()->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não foi possível rejeitar')->body($e->getMessage())->warning()->send();
                    }
                }),

            Actions\Action::make('avaliar_ia')
                ->label('Avaliar com IA')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->action(function (PropostaComercialAiEvaluator $evaluator) {
                    try {
                        $evaluator->evaluate($this->record);

                        Notification::make()
                            ->title('Proposta avaliada com sucesso.')
                            ->success()
                            ->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()
                            ->title('Falha ao avaliar')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('ver_solicitacao')
                ->label('Ver Solicitação de Locação')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->visible(fn () => filled($record->solicitacao_locacao_id))
                ->url(fn () => SolicitacaoLocacaoResource::getUrl('edit', ['record' => $record->solicitacao_locacao_id])),

            Actions\Action::make('imprimir')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->url(fn () => route('proposta-comercial.print', $this->record))
                ->openUrlInNewTab(),
        ];
    }
}
