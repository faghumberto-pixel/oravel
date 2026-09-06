<?php

namespace App\Filament\Resources;

use App\Domain\Fleet\Models\ContractMeasurement;
use App\Domain\Fleet\Models\ContractMeasurementExtra;
use App\Filament\Resources\ContractMeasurementResource\Pages;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Revisão administrativa das medições mensais consolidadas -- geradas em
 * rascunho por App\Console\Commands\GenerateMonthlyMeasurements, sem
 * formulário de criação manual aqui de propósito (o cálculo de pró-rata/
 * excedente não faz sentido digitado à mão; ver
 * ContractMeasurementService).
 */
class ContractMeasurementResource extends Resource
{
    protected static ?string $model = ContractMeasurement::class;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationParentItem = 'Gestão Comercial';

    protected static ?string $navigationLabel = 'Medições de Contrato';

    protected static ?string $modelLabel = 'Medição de Contrato';

    protected static ?string $pluralModelLabel = 'Medições de Contrato';

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            InfolistSection::make('Medição')
                ->columns(3)
                ->schema([
                    TextEntry::make('contract.contract_number')->label('Contrato'),
                    TextEntry::make('reference_period_start')->label('Início do Período')->date('d/m/Y'),
                    TextEntry::make('reference_period_end')->label('Fim do Período')->date('d/m/Y'),
                    TextEntry::make('prorated_days')
                        ->label('Dias Vigentes / Dias do Período')
                        ->state(fn (ContractMeasurement $record) => "{$record->prorated_days} / {$record->total_days_in_period}"),
                    TextEntry::make('total_base_amount')->label('Valor Base')->money('BRL'),
                    TextEntry::make('total_excess_hours_amount')->label('Excedente de Horas')->money('BRL'),
                    TextEntry::make('total_extras_amount')->label('Extras')->money('BRL'),
                    TextEntry::make('total_amount')->label('Total')->money('BRL')->weight('bold'),
                    TextEntry::make('status')
                        ->label('Status')
                        ->badge()
                        ->formatStateUsing(fn (string $state) => ContractMeasurement::statusLabels()[$state] ?? $state),
                    TextEntry::make('rejection_reason')
                        ->label('Motivo da Rejeição')
                        ->columnSpanFull()
                        ->visible(fn (ContractMeasurement $record) => filled($record->rejection_reason)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contract.contract_number')
                    ->label('Contrato')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reference_period_start')
                    ->label('Período')
                    ->formatStateUsing(fn (ContractMeasurement $record) => $record->reference_period_start->format('d/m/Y').' – '.$record->reference_period_end->format('d/m/Y'))
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_prorated')
                    ->label('Pró-rata')
                    ->getStateUsing(fn (ContractMeasurement $record) => $record->isProrated())
                    ->boolean(),
                Tables\Columns\TextColumn::make('total_base_amount')->label('Base')->money('BRL')->sortable(),
                Tables\Columns\TextColumn::make('total_excess_hours_amount')->label('Excedente')->money('BRL')->sortable(),
                Tables\Columns\TextColumn::make('total_extras_amount')->label('Extras')->money('BRL')->sortable(),
                Tables\Columns\TextColumn::make('total_amount')->label('Total')->money('BRL')->weight('bold')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ContractMeasurement::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        ContractMeasurement::STATUS_DRAFT => 'gray',
                        ContractMeasurement::STATUS_AWAITING_APPROVAL => 'warning',
                        ContractMeasurement::STATUS_APPROVED => 'info',
                        ContractMeasurement::STATUS_INVOICED => 'success',
                        ContractMeasurement::STATUS_REJECTED => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('reference_period_start', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(ContractMeasurement::statusLabels()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('adicionarExtra')
                    ->label('Adicionar Extra')
                    ->icon('heroicon-o-plus-circle')
                    ->color('gray')
                    ->visible(fn (ContractMeasurement $record) => $record->status === ContractMeasurement::STATUS_DRAFT)
                    ->form([
                        Select::make('type')
                            ->label('Tipo')
                            ->options(ContractMeasurementExtra::typeLabels())
                            ->required()
                            ->native(false),
                        TextInput::make('description')->label('Descrição'),
                        TextInput::make('amount')->label('Valor')->numeric()->prefix('R$')->required(),
                    ])
                    ->action(function (ContractMeasurement $record, array $data) {
                        $record->extras()->create($data);
                        $record->recalculateTotals();

                        Notification::make()->title('Extra adicionado')->success()->send();
                    }),

                Tables\Actions\Action::make('enviar')
                    ->label('Enviar para Aprovação')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(fn (ContractMeasurement $record) => $record->status === ContractMeasurement::STATUS_DRAFT)
                    ->requiresConfirmation()
                    ->action(function (ContractMeasurement $record) {
                        $record->submit();

                        Notification::make()->title('Medição enviada para aprovação')->success()->send();
                    }),

                Tables\Actions\Action::make('aprovar')
                    ->label('Aprovar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ContractMeasurement $record) => $record->status === ContractMeasurement::STATUS_AWAITING_APPROVAL)
                    ->requiresConfirmation()
                    ->action(function (ContractMeasurement $record) {
                        $record->approve(auth()->user());

                        Notification::make()->title('Medição aprovada')->success()->send();
                    }),

                Tables\Actions\Action::make('rejeitar')
                    ->label('Rejeitar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ContractMeasurement $record) => $record->status === ContractMeasurement::STATUS_AWAITING_APPROVAL)
                    ->form([
                        Textarea::make('reason')->label('Motivo da rejeição')->required(),
                    ])
                    ->action(function (ContractMeasurement $record, array $data) {
                        $record->reject(auth()->user(), $data['reason']);

                        Notification::make()->title('Medição rejeitada')->warning()->send();
                    }),

                Tables\Actions\Action::make('faturar')
                    ->label('Faturar')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->visible(fn (ContractMeasurement $record) => $record->status === ContractMeasurement::STATUS_APPROVED)
                    ->requiresConfirmation()
                    ->modalDescription('Isso vai gerar uma Conta a Receber com o valor total desta medição.')
                    ->form([
                        DatePicker::make('due_date')
                            ->label('Vencimento da Cobrança')
                            ->default(now()->addDays(15))
                            ->required(),
                    ])
                    ->action(function (ContractMeasurement $record, array $data) {
                        $record->markInvoiced($data['due_date']);

                        Notification::make()->title('Medição faturada')->body('Conta a receber gerada com sucesso.')->success()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContractMeasurements::route('/'),
            'view' => Pages\ViewContractMeasurement::route('/{record}'),
        ];
    }
}
