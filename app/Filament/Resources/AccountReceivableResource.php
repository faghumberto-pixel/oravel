<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountReceivableResource\Pages;
use App\Models\AccountReceivable;
use App\Support\Tenancy;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AccountReceivableResource extends Resource
{
    protected static ?string $model = AccountReceivable::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?string $navigationLabel = 'Contas a Receber';

    protected static ?string $modelLabel = 'Conta a Receber';

    protected static ?string $pluralModelLabel = 'Contas a Receber';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Informações da Conta')
                ->schema([
                    TextInput::make('description')->label('Descrição')->required()->maxLength(255),

                    Select::make('client_id')
                        ->label('Cliente')
                        ->relationship('client', 'name', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->searchable()->preload(),

                    Select::make('contract_id')
                        ->label('Contrato')
                        ->relationship('contract', 'contract_number', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->searchable()->preload(),

                    Select::make('billing_plan_id')
                        ->label('Plano de Cobrança (Dinâmico)')
                        ->relationship('billingPlan', 'id', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->client?->name.' - '.($record->frequency ?? ''))
                        ->searchable()->preload()
                        ->visible(fn () => Tenancy::current()?->isFieldVisible('billing_plan_id') ?? true),

                    TextInput::make('amount')->label('Valor')->numeric()->prefix('R$')->required(),
                    DatePicker::make('due_date')->label('Vencimento')->required()->live(),
                    DatePicker::make('payment_date')->label('Recebimento')->visible(fn ($get) => $get('status') === 'pago'),

                    Select::make('status')
                        ->options(['pendente' => 'Pendente', 'pago' => 'Recebido', 'atrasado' => 'Atrasado'])
                        ->required()->default('pendente')->live()->native(false),
                ])->columns(2),

            Section::make('Alocação e Origem')
                ->schema([
                    Select::make('branch_id')
                        ->label('Filial')
                        ->relationship('branch', 'name', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->searchable()->preload(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description')->label('Descrição')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('client.name')->label('Cliente')->searchable(),
                Tables\Columns\TextColumn::make('amount')->label('Valor')->money('BRL'),
                Tables\Columns\TextColumn::make('due_date')->label('Vencimento')->date('d/m/Y'),
                Tables\Columns\TextColumn::make('multa_valor')->label('Multa')->money('BRL')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge()->colors([
                    'warning' => 'pendente', 'success' => 'pago', 'danger' => 'atrasado',
                ])->formatStateUsing(fn ($state) => ucfirst($state)),
            ])
            ->actions([
                Tables\Actions\Action::make('registrarRecebimento')
                    ->label('Dar Baixa')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (AccountReceivable $record) => $record->status !== 'pago')
                    ->form(fn (AccountReceivable $record) => [
                        DatePicker::make('payment_date')->label('Data de Recebimento')->required()->default(now()),
                        TextInput::make('multa_percentual')
                            ->label('Multa Aplicada (%)')
                            ->numeric()
                            ->default(fn () => $record->contract?->multa_rescisoria)
                            ->visible(fn () => Tenancy::current()?->hasModuleEnabled('contas_a_receber') ?? true)
                            ->disabled(fn () => ! (Tenancy::current()?->hasModuleEnabled('contas_a_receber') ?? true)),
                    ])
                    ->action(function (AccountReceivable $record, array $data) {
                        $record->payment_date = $data['payment_date'];
                        $record->status = 'pago';

                        if (Tenancy::current()?->hasModuleEnabled('contas_a_receber')) {
                            $record->multa_percentual = $data['multa_percentual'] ?? null;
                            $record->multa_valor = $record->calculateLateFee();
                        }

                        $record->save();

                        Notification::make()->title('Recebimento registrado')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageAccountReceivables::route('/')];
    }
}
