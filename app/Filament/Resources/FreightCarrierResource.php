<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FreightCarrierResource\Pages;
use App\Models\FreightCarrier;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class FreightCarrierResource extends Resource
{
    protected static ?string $model = FreightCarrier::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Logística';

    protected static ?string $navigationLabel = 'Transportadoras';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('nome')
                ->label('Nome / Razão Social')
                ->required()
                ->columnSpanFull(),
            Forms\Components\TextInput::make('documento')
                ->label('CNPJ / CPF'),
            Forms\Components\TextInput::make('contato_nome')
                ->label('Nome do Contato'),
            Forms\Components\TextInput::make('contato_telefone')
                ->label('Telefone')
                ->tel(),

            Forms\Components\CheckboxList::make('vehicle_types')
                ->label('Tipos de Veículo Disponíveis')
                ->options(FreightCarrier::vehicleTypeLabels())
                ->columns(3)
                ->columnSpanFull(),

            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('insurance_policy_number')
                    ->label('Número da Apólice de Seguro'),
                Forms\Components\TextInput::make('insurance_coverage_value')
                    ->label('Valor de Cobertura do Seguro')
                    ->numeric()->minValue(0)->prefix('R$'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome')->label('Transportadora')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('documento')->label('CNPJ/CPF'),
                Tables\Columns\TextColumn::make('contato_nome')->label('Contato'),
                Tables\Columns\TextColumn::make('contato_telefone')->label('Telefone'),
                Tables\Columns\TextColumn::make('vehicle_types')
                    ->label('Veículos')
                    ->formatStateUsing(fn (?array $state) => $state
                        ? collect($state)->map(fn ($v) => FreightCarrier::vehicleTypeLabels()[$v] ?? $v)->implode(', ')
                        : '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('freight_records_count')
                    ->label('Fretes Realizados')
                    ->counts('freightRecords'),
            ])
            ->filters([
                Tables\Filters\Filter::make('ativas_no_mes')
                    ->label('Com Frete no Mês')
                    ->query(fn ($query) => $query->whereHas('freightRecords', fn ($q) => $q->where('data', '>=', now()->startOfMonth())))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFreightCarriers::route('/'),
            'create' => Pages\CreateFreightCarrier::route('/create'),
            'edit' => Pages\EditFreightCarrier::route('/{record}/edit'),
        ];
    }
}
