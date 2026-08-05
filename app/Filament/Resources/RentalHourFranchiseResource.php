<?php

namespace App\Filament\Resources;

use App\Domain\Fleet\Models\RentalHourFranchise;
use App\Filament\Resources\RentalHourFranchiseResource\Pages;
use App\Support\Tenancy;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * RentalHourFranchise só é relevante pra contratos com
 * billing_type = Contract::BILLING_FRANQUIA_EXCEDENTE (ver
 * Contract::usesHourFranchise()) -- este Resource não força essa checagem
 * no form por simplicidade, é responsabilidade de quem cadastra escolher o
 * contrato certo.
 */
class RentalHourFranchiseResource extends Resource
{
    protected static ?string $model = RentalHourFranchise::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $navigationLabel = 'Franquias de Horas';

    protected static ?string $modelLabel = 'Franquia de Horas';

    protected static ?string $pluralModelLabel = 'Franquias de Horas';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Franquia de Locação')
                ->schema([
                    Select::make('contract_id')
                        ->label('Contrato')
                        ->relationship('contract', 'contract_number', fn ($query) => $query->where('tenant_id', Tenancy::current()?->id))
                        ->searchable()->preload()->required(),

                    Select::make('period_type')
                        ->label('Período')
                        ->options(RentalHourFranchise::periodTypeLabels())
                        ->required()->default(RentalHourFranchise::PERIOD_MENSAL)->native(false),

                    TextInput::make('included_hours_per_period')
                        ->label('Horas Incluídas')
                        ->numeric()->suffix('h')->required(),

                    TextInput::make('overage_rate_per_hour')
                        ->label('Valor da Hora Excedente')
                        ->numeric()->prefix('R$')->required(),

                    DatePicker::make('effective_from')
                        ->label('Vigente a partir de')
                        ->required()->default(now()),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('contract.contract_number')->label('Contrato')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('contract.client.name')->label('Cliente')->searchable(),
                Tables\Columns\TextColumn::make('period_type')->label('Período')
                    ->formatStateUsing(fn (string $state) => RentalHourFranchise::periodTypeLabels()[$state] ?? $state),
                Tables\Columns\TextColumn::make('included_hours_per_period')->label('Horas Incluídas')->suffix('h')->sortable(),
                Tables\Columns\TextColumn::make('overage_rate_per_hour')->label('Hora Excedente')->money('BRL')->sortable(),
                Tables\Columns\TextColumn::make('effective_from')->label('Vigente desde')->date('d/m/Y')->sortable(),
            ])
            ->defaultSort('effective_from', 'desc')
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
        return ['index' => Pages\ManageRentalHourFranchises::route('/')];
    }
}
