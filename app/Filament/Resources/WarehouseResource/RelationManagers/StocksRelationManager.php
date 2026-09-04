<?php

namespace App\Filament\Resources\WarehouseResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StocksRelationManager extends RelationManager
{
    protected static string $relationship = 'stocks';

    protected static ?string $recordTitleAttribute = 'part.name';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('part.sku')
                ->readOnly(),
            Forms\Components\TextInput::make('current_quantity')
                ->readOnly(),
            Forms\Components\TextInput::make('reserved_quantity')
                ->readOnly(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('part.sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('part.name')
                    ->label('Peça')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_quantity')
                    ->label('Quantidade Atual')
                    ->numeric(decimals: 2),
                Tables\Columns\TextColumn::make('reserved_quantity')
                    ->label('Reservada')
                    ->numeric(decimals: 2),
                Tables\Columns\TextColumn::make('available_quantity')
                    ->label('Disponível')
                    ->numeric(decimals: 2),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
