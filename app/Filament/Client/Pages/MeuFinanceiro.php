<?php

namespace App\Filament\Client\Pages;

use App\Models\AccountReceivable;
use App\Models\Client;
use App\Services\AsaasService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;

/**
 * "Gerar 2ª via" chama AsaasService::createPayment() sob demanda -- não
 * gera boleto no cadastro do AccountReceivable, só quando o Client pedir.
 * asaas_payment_id/asaas_invoice_url/asaas_boleto_url são reaproveitados
 * se já existirem (evita criar cobrança duplicada na Asaas a cada clique).
 */
class MeuFinanceiro extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Meu Financeiro';

    protected static string $view = 'filament.client.pages.meu-financeiro';

    public function table(Table $table): Table
    {
        /** @var Client $client */
        $client = $this->guard()->user();

        return $table
            ->query(
                AccountReceivable::withoutGlobalScope('tenant')
                    ->where('tenant_id', $client->tenant_id)
                    ->where('client_id', $client->id)
            )
            ->columns([
                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição'),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Vencimento')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->actions([
                Tables\Actions\Action::make('verBoleto')
                    ->label('Ver boleto/PIX')
                    ->icon('heroicon-o-document-text')
                    ->visible(fn (AccountReceivable $record) => filled($record->asaas_invoice_url))
                    ->url(fn (AccountReceivable $record) => $record->asaas_invoice_url)
                    ->openUrlInNewTab(),
                Tables\Actions\Action::make('gerarSegundaVia')
                    ->label('Gerar 2ª via')
                    ->icon('heroicon-o-document-plus')
                    ->visible(fn (AccountReceivable $record) => blank($record->asaas_invoice_url))
                    ->form([
                        Forms\Components\Radio::make('billingType')
                            ->label('Forma de pagamento')
                            ->options([
                                'BOLETO' => 'Boleto',
                                'PIX' => 'PIX',
                            ])
                            ->default('BOLETO')
                            ->required(),
                    ])
                    ->action(function (AccountReceivable $record, array $data) {
                        /** @var Client $client */
                        $client = $this->guard()->user();

                        try {
                            $payment = app(AsaasService::class)->createPayment([
                                'customer' => $client->tenant->asaas_customer_id,
                                'billingType' => $data['billingType'],
                                'value' => (float) $record->amount,
                                'dueDate' => $record->due_date->toDateString(),
                                'description' => $record->description,
                            ]);
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Não foi possível gerar a cobrança agora')
                                ->body('Tente novamente em instantes ou contate a locadora.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'asaas_payment_id' => $payment['id'] ?? null,
                            'asaas_invoice_url' => $payment['invoiceUrl'] ?? null,
                            'asaas_boleto_url' => $payment['bankSlipUrl'] ?? null,
                        ]);

                        Notification::make()
                            ->title($data['billingType'] === 'PIX' ? 'Cobrança PIX gerada' : 'Boleto gerado')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('espelho')
                    ->label('Baixar Espelho')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (AccountReceivable $record) => route('cliente.receivable.mirror', $record))
                    ->openUrlInNewTab(),
            ])
            ->defaultSort('due_date', 'desc');
    }

    private function guard(): Guard
    {
        return Auth::guard('client');
    }
}
