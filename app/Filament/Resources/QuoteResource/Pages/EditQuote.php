<?php

namespace App\Filament\Resources\QuoteResource\Pages;

use App\Filament\Resources\QuoteResource;
use App\Mail\GenericPdfMail;
use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

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
                ->form([
                    // Prefill com o e-mail de contato geral do Client (item 8
                    // da auditoria); cai pro e-mail financeiro se o cliente
                    // nao tiver o geral cadastrado. Sempre editável aqui --
                    // o remetente pode querer mandar pra outro endereço numa
                    // ocasião específica sem precisar editar o cadastro.
                    Forms\Components\TextInput::make('client_email')
                        ->label('E-mail do cliente')
                        ->email()
                        ->required()
                        ->default(fn () => $record->client->email ?: $record->client->email_financial),
                ])
                ->action(function (array $data) use ($record) {
                    try {
                        $record->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Não foi possível enviar')->body($e->getMessage())->warning()->send();

                        return;
                    }

                    $pdfContent = Pdf::loadView('pdf.quote', [
                        'quote' => $record->fresh()->load(['client', 'items']),
                        'generatedAt' => now()->format('d/m/Y H:i'),
                    ])->output();

                    $approvalUrl = route('quotes.public-approval', $record->approval_token);

                    Mail::to($data['client_email'])->send(new GenericPdfMail(
                        subjectLine: 'Orçamento — '.$record->client->name,
                        greeting: 'Olá!',
                        bodyText: "Segue o orçamento em anexo.\n\nPra aprovar ou reprovar, acesse o link abaixo:\n{$approvalUrl}",
                        pdfContent: $pdfContent,
                        pdfFilename: "orcamento-{$record->id}.pdf",
                        senderDisplayName: auth()->user()?->tenant?->name,
                        replyToAddress: auth()->user()?->email,
                    ));

                    Notification::make()->title('Orçamento enviado por e-mail.')->success()->send();
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
                ->modalDescription('Reúne o PDF do orçamento e cria a Conta a Receber correspondente, na fila que o Financeiro já usa.')
                ->form([
                    Forms\Components\DatePicker::make('due_date')
                        ->label('Vencimento')
                        ->required()
                        ->default(fn () => now()->addDays(30)),
                ])
                ->action(function (array $data) use ($record) {
                    try {
                        $record->forwardToFinanceiro(Carbon::parse($data['due_date']));
                        Notification::make()->title('Encaminhado ao Financeiro.')->body('Conta a Receber criada.')->success()->send();
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
