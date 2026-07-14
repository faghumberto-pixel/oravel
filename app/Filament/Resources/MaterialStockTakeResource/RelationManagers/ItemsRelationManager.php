<?php

namespace App\Filament\Resources\MaterialStockTakeResource\RelationManagers;

use App\Models\MaterialStockTake;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Uma linha por Material -- ja' populada inteira ao criar o inventario
 * (MaterialStockTake::populateFromMaterials()), sem criacao/exclusao
 * manual aqui. So' a quantidade contada e' editavel, e so' enquanto o
 * inventario estiver em rascunho.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Itens da Contagem';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        $isDraft = $this->getOwnerRecord()->status === MaterialStockTake::STATUS_RASCUNHO;

        return $table
            ->recordTitleAttribute('material.name')
            ->columns([
                Tables\Columns\TextColumn::make('material.sku')
                    ->label('SKU')
                    ->searchable(),
                Tables\Columns\TextColumn::make('material.name')
                    ->label('Material')
                    ->searchable(),
                Tables\Columns\TextColumn::make('expected_quantity')
                    ->label('Saldo do Sistema')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd(),
                Tables\Columns\TextInputColumn::make('counted_quantity')
                    ->label('Quantidade Contada')
                    ->type('number')
                    ->step(0.01)
                    ->disabled(! $isDraft)
                    ->rules(['nullable', 'numeric', 'min:0']),
                Tables\Columns\TextColumn::make('difference')
                    ->label('Diferença')
                    ->numeric(decimalPlaces: 2)
                    ->color(fn ($state) => $state === null ? 'gray' : ($state == 0 ? 'success' : 'danger'))
                    ->placeholder('—')
                    ->alignEnd(),
            ])
            ->filters([
                Tables\Filters\Filter::make('divergentes')
                    ->label('Só divergentes')
                    ->query(fn ($query) => $query->whereNotNull('counted_quantity')->whereColumn('counted_quantity', '!=', 'expected_quantity')),
                Tables\Filters\Filter::make('nao_contados')
                    ->label('Ainda não contados')
                    ->query(fn ($query) => $query->whereNull('counted_quantity')),
            ])
            ->defaultSort('material.name')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
