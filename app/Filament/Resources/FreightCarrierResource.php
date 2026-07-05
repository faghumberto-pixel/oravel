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
                Tables\Columns\TextColumn::make('freight_records_count')
                    ->label('Fretes Realizados')
                    ->counts('freightRecords'),
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
