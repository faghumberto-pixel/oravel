<?php

namespace App\Filament\Resources\PropostaComercialResource\Pages;

use App\Filament\Resources\PropostaComercialResource;
use App\Filament\Resources\SolicitacaoLocacaoResource;
use App\Models\PropostaComercial;
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
