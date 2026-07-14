<?php

namespace App\Filament\Resources\MaterialRequestResource\RelationManagers;

use App\Support\Tenancy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Itens da Requisição';

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
                ->required()
                ->default(1),
            Forms\Components\TextInput::make('brand')
                ->label('Marca (opcional)')
                ->maxLength(255),
            Forms\Components\TextInput::make('cost_price')
                ->label('Preço Praticado (opcional)')
                ->numeric()
                ->prefix('R$'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('material.name')
            ->columns([
                Tables\Columns\TextColumn::make('material.sku')->label('SKU'),
                Tables\Columns\TextColumn::make('material.name')->label('Material'),
                Tables\Columns\TextColumn::make('quantity')->label('Qtd.')->numeric(),
                Tables\Columns\TextColumn::make('brand')->label('Marca')->placeholder('—'),
                Tables\Columns\TextColumn::make('cost_price')->label('Preço')->money('BRL')->placeholder('—'),
                Tables\Columns\TextColumn::make('quality_rating')->label('Nota Qualidade')->placeholder('—'),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
