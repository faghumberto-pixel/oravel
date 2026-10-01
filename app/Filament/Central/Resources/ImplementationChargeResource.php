<?php

namespace App\Filament\Central\Resources;

use App\Filament\Central\Resources\ImplementationChargeResource\Pages;
use App\Models\ImplementationCharge;
use App\Services\AsaasService;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Gestão da taxa de implantação dos clientes: cada parcela, seu valor,
 * vencimento e se foi paga. Somente leitura + cancelar cobrança avulsa; o valor
 * e as parcelas são definidos no Contrato/Empresa (Tenant).
 */
class ImplementationChargeResource extends Resource
{
    protected static ?string $model = ImplementationCharge::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Gestão SaaS';

    protected static ?string $navigationLabel = 'Implantações';

    protected static ?string $modelLabel = 'Parcela de Implantação';

    protected static ?string $pluralModelLabel = 'Implantações';

    protected static bool $isScopedToTenant = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes()->with('tenant');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            ImplementationCharge::PENDENTE => 'Pendente',
            ImplementationCharge::PAGO => 'Pago',
            ImplementationCharge::ATRASADO => 'Atrasado',
            ImplementationCharge::CANCELADO => 'Cancelado',
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')->label('Cliente')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('installment')->label('Parcela')
                    ->state(fn (ImplementationCharge $r) => "{$r->installment_number}/{$r->installments_total}"),
                Tables\Columns\TextColumn::make('amount')->label('Valor')->money('BRL')->sortable()
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->money('BRL')->label('Total')),
                Tables\Columns\TextColumn::make('mode')->label('Forma')->badge()->color('gray')
                    ->state(fn (ImplementationCharge $r) => $r->included_in_subscription ? 'Somada à mensalidade' : 'Cobrança avulsa'),
                Tables\Columns\TextColumn::make('due_date')->label('Vencimento')->date('d/m/Y')->sortable()
                    ->description(fn (ImplementationCharge $r) => $r->included_in_subscription ? 'previsão' : null),
                Tables\Columns\TextColumn::make('status')->label('Situação')->badge()
                    ->formatStateUsing(fn ($state) => static::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        ImplementationCharge::PAGO => 'success',
                        ImplementationCharge::ATRASADO => 'danger',
                        ImplementationCharge::CANCELADO => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('paid_at')->label('Pago em')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('Situação')->options(static::statusLabels()),
                Tables\Filters\TernaryFilter::make('included_in_subscription')->label('Forma')
                    ->trueLabel('Somada à mensalidade')->falseLabel('Cobrança avulsa'),
                Tables\Filters\SelectFilter::make('tenant_id')->label('Cliente')->relationship('tenant', 'name')->searchable(),
            ])
            ->actions([
                Tables\Actions\Action::make('open_invoice')
                    ->label('Ver cobrança')->icon('heroicon-o-arrow-top-right-on-square')->color('gray')
                    ->visible(fn (ImplementationCharge $r) => filled($r->invoice_url))
                    ->url(fn (ImplementationCharge $r) => $r->invoice_url, shouldOpenInNewTab: true),
                Tables\Actions\Action::make('cancel_charge')
                    ->label('Cancelar cobrança')->icon('heroicon-o-x-circle')->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Cancela esta cobrança no Asaas. O cliente não poderá mais pagá-la por esse link.')
                    ->visible(fn (ImplementationCharge $r) => ! $r->included_in_subscription
                        && filled($r->asaas_payment_id)
                        && in_array($r->status, [ImplementationCharge::PENDENTE, ImplementationCharge::ATRASADO], true))
                    ->action(function (ImplementationCharge $r) {
                        if (app(AsaasService::class)->cancelPayment($r->asaas_payment_id)) {
                            $r->update(['status' => ImplementationCharge::CANCELADO, 'paid_at' => null]);
                            Notification::make()->title('Cobrança cancelada')->success()->send();
                        } else {
                            Notification::make()->title('O Asaas não cancelou a cobrança')->body('Ela pode já estar paga. Confira no painel do Asaas.')->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListImplementationCharges::route('/')];
    }
}
