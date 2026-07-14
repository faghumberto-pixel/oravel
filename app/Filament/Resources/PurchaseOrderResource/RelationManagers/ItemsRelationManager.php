<?php

namespace App\Filament\Resources\PurchaseOrderResource\RelationManagers;

use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * quantity_received e' so'-leitura aqui de proposito -- quem atualiza e'
 * o Recebimento (GoodsReceipt), nao edicao manual da Ordem de Compra.
 */
class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Itens da Ordem de Compra';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('material_id')
                ->label('Material')
                ->relationship('material', 'name', fn (Builder $query) => $query->where('tenant_id', Tenancy::current()?->id))
                ->searchable()
                ->preload()
                ->required(),
            Forms\Components\TextInput::make('quantity')
                ->label('Quantidade')
                ->numeric()
                ->required(),
            Forms\Components\TextInput::make('unit_price')
                ->label('Preço Unitário')
                ->numeric()
                ->prefix('R$')
                ->required(),
        ])->columns(3);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('material.name')
            ->columns([
                Tables\Columns\TextColumn::make('material.sku')->label('SKU'),
                Tables\Columns\TextColumn::make('material.name')->label('Material'),
                Tables\Columns\TextColumn::make('quantity')->label('Qtd. Pedida')->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('unit_price')->label('Preço Unit.')->money('BRL'),
                Tables\Columns\TextColumn::make('quantity_received')->label('Qtd. Recebida')->numeric(decimalPlaces: 2),
                Tables\Columns\TextColumn::make('pending_quantity')->label('Pendente')->numeric(decimalPlaces: 2),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->material_request_id === null),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->material_request_id === null),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => $this->getOwnerRecord()->material_request_id === null),
            ]);
    }
}
