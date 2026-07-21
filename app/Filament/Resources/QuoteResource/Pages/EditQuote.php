<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use App\Models\Quote;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditQuote extends EditRecord
{
    protected static string $resource = QuoteResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Quote $record */
        $record = $this->getRecord();

        return [
            Actions\Action::make('baixar_pdf')
                ->label('Baixar PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->url(fn () => route('quotes.pdf', $record))
                ->openUrlInNewTab(),

            Actions\Action::make('enviar')
                ->label('Enviar ao Cliente')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->visible(fn () => $record->status === Quote::STATUS_RASCUNHO)
                ->requiresConfirmation()
                ->action(function () use ($record) {
                    try {
                        $record->send();
                        Notification::make()->title('Orçamento enviado.')->success()->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não foi possível enviar')->body($e->getMessage())->warning()->send();
                    }
                }),

            Actions\Action::make('aprovar')
                ->label('Marcar como Aprovado')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $record->status === Quote::STATUS_ENVIADO)
                ->requiresConfirmation()
                ->modalDescription('Confirma que o cliente aprovou este orçamento?')
                ->action(function () use ($record) {
                    try {
                        $record->approve();
                        Notification::make()->title('Orçamento aprovado.')->success()->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não foi possível aprovar')->body($e->getMessage())->warning()->send();
                    }
                }),

            Actions\Action::make('reprovar')
                ->label('Marcar como Reprovado')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $record->status === Quote::STATUS_ENVIADO)
                ->requiresConfirmation()
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Motivo da reprovação')
                        ->required(),
                ])
                ->action(function (array $data) use ($record) {
                    try {
                        $record->reject($data['reason']);
                        Notification::make()->title('Orçamento marcado como reprovado.')->success()->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não foi possível reprovar')->body($e->getMessage())->warning()->send();
                    }
                }),

            Actions\Action::make('encaminhar_financeiro')
                ->label('Encaminhar ao Financeiro')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->visible(fn () => $record->status === Quote::STATUS_APROVADO && ! $record->financeiro_forwarded_at)
                ->requiresConfirmation()
                ->modalDescription('Reúne o PDF do orçamento e sinaliza pro Financeiro que está pronto pra cobrança.')
                ->action(function () use ($record) {
                    try {
                        $record->forwardToFinanceiro();
                        Notification::make()->title('Encaminhado ao Financeiro.')->success()->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não foi possível encaminhar')->body($e->getMessage())->warning()->send();
                    }
                }),

            Actions\Action::make('concluir')
                ->label('Concluir Processo')
                ->icon('heroicon-o-flag')
                ->color('primary')
                ->visible(fn () => $record->status === Quote::STATUS_APROVADO)
                ->requiresConfirmation()
                ->action(function () use ($record) {
                    try {
                        $record->complete();
                        Notification::make()->title('Orçamento concluído.')->success()->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não foi possível concluir')->body($e->getMessage())->warning()->send();
                    }
                }),

            Actions\DeleteAction::make()
                ->visible(fn () => $record->status === Quote::STATUS_RASCUNHO),
        ];
    }
}
