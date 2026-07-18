<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillingPlanResource\Pages;
use App\Models\BillingPlan;
use App\Support\Tenancy;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BillingPlanResource extends Resource
{
    protected static ?string $model = BillingPlan::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-date-range';

    protected static ?string $navigationGroup = 'Financeiro';

    protected static ?string $navigationLabel = 'Planos de Cobrança';

    protected static ?string $modelLabel = 'Plano de Cobrança';

    protected static ?string $pluralModelLabel = 'Planos de Cobrança';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Plano de Cobrança Dinâmico')
                ->schema([
                    Select::make('client_id')
                        ->label('Cliente')
                        ->relationship('client', 'name', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->searchable()->preload(),

                    Select::make('contract_id')
                        ->label('Contrato')
                        ->relationship('contract', 'contract_number', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->searchable()->preload(),

                    Select::make('frequency')
                        ->label('Frequência')
                        ->options(BillingPlan::frequencyLabels())
                        ->required()->default(BillingPlan::FREQUENCY_MONTHLY)->native(false),

                    TextInput::make('amount')
                        ->label('Valor')
                        ->numeric()->prefix('R$')->required(),

                    TextInput::make('due_day')
                        ->label('Dia de Vencimento')
                        ->numeric()->minValue(1)->maxValue(31)->default(10)->required(),

                    Toggle::make('active')
                        ->label('Ativo')
                        ->default(true),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('client.name')->label('Cliente')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('contract.contract_number')->label('Contrato'),
                Tables\Columns\TextColumn::make('frequency')->label('Frequência')
                    ->formatStateUsing(fn (string $state) => BillingPlan::frequencyLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('amount')->label('Valor')->money('BRL'),
                Tables\Columns\TextColumn::make('due_day')->label('Dia Venc.'),
                Tables\Columns\IconColumn::make('active')->label('Ativo')->boolean(),
            ])
            ->actions([
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
        return ['index' => Pages\ManageBillingPlans::route('/')];
    }
}
