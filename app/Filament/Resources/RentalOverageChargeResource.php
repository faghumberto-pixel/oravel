<?php

namespace App\Filament\Resources;

use App\Domain\Fleet\Models\RentalOverageCharge;
use App\Filament\Resources\RentalOverageChargeResource\Pages;
use App\Models\Asset;
use App\Support\Tenancy;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class RentalOverageChargeResource extends Resource
{
    protected static ?string $model = RentalOverageCharge::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationLabel = 'Excedentes de Locação';

    protected static ?string $modelLabel = 'Excedente de Locação';

    protected static ?string $pluralModelLabel = 'Excedentes de Locação';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Apuração de Excedente')
                ->schema([
                    Select::make('contract_id')
                        ->label('Contrato')
                        ->relationship('contract', 'contract_number', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->searchable()->preload()->required(),

                    Select::make('asset_id')
                        ->label('Ativo')
                        ->options(fn () => Asset::selectOptions())
                        ->searchable()->required(),

                    DatePicker::make('period_start')->label('Início do Período')->required(),
                    DatePicker::make('period_end')->label('Fim do Período')->required(),

                    TextInput::make('hours_used')->label('Horas Utilizadas')->numeric()->suffix('h')->required(),
                    TextInput::make('hours_included')->label('Horas da Franquia')->numeric()->suffix('h')->required(),
                    TextInput::make('hours_overage')->label('Horas Excedentes')->numeric()->suffix('h')->required(),
                    TextInput::make('amount')->label('Valor a Cobrar')->numeric()->prefix('R$')->required(),

                    Select::make('status')
                        ->label('Status')
                        ->options(RentalOverageCharge::statusLabels())
                        ->required()->default(RentalOverageCharge::STATUS_PENDING)->native(false),

                    Textarea::make('conflict_reason')
                        ->label('Motivo do Conflito')
                        ->columnSpanFull()
                        ->visible(fn (?RentalOverageCharge $record) => $record?->status === RentalOverageCharge::STATUS_CONFLICT)
                        ->disabled(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contract.contract_number')->label('Contrato')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('asset.name')->label('Ativo')->searchable(),
                Tables\Columns\TextColumn::make('period_start')->label('Período')
                    ->formatStateUsing(fn (RentalOverageCharge $record) => $record->period_start->format('d/m/Y').' – '.$record->period_end->format('d/m/Y')),
                Tables\Columns\TextColumn::make('hours_overage')->label('Excedente')->suffix('h')->sortable(),
                Tables\Columns\TextColumn::make('amount')->label('Valor')->money('BRL')->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => RentalOverageCharge::statusLabels()[$state] ?? $state)
                    ->color(fn (string $state) => match ($state) {
                        RentalOverageCharge::STATUS_PENDING => 'warning',
                        RentalOverageCharge::STATUS_INVOICED => 'success',
                        RentalOverageCharge::STATUS_CANCELLED => 'danger',
                        RentalOverageCharge::STATUS_CONFLICT => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('period_start', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(RentalOverageCharge::statusLabels()),
            ])
            ->actions([
                Tables\Actions\Action::make('aprovar')
                    ->label('Aprovar e Gerar Cobrança')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (RentalOverageCharge $record) => $record->status === RentalOverageCharge::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->modalDescription('Isso vai gerar uma Conta a Receber com o valor calculado. Confirme os dados antes de aprovar.')
                    ->form([
                        DatePicker::make('due_date')
                            ->label('Vencimento da Cobrança')
                            ->default(now()->addDays(15))
                            ->required(),
                    ])
                    ->action(function (RentalOverageCharge $record, array $data) {
                        $record->approve(auth()->user(), $data['due_date']);

                        Notification::make()
                            ->title('Excedente aprovado')
                            ->body('Conta a receber gerada com sucesso.')
                            ->success()
                            ->send();
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
        return ['index' => Pages\ManageRentalOverageCharges::route('/')];
    }
}
