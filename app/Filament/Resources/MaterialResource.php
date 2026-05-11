<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialResource\Pages;
use App\Filament\Resources\MaterialResource\RelationManagers;
use App\Models\Material;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth; // Importação necessária para o filtro de Tenant

class MaterialResource extends Resource
{ 
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $model = Material::class;

    protected static ?string $modelLabel = 'Material';
    protected static ?string $pluralModelLabel = 'Materiais';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'GESTAO DE ESTOQUE';

    // CORREÇÃO: Filtro de Tenant isolado para Materiais (Remove o erro de technician_id)
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', Auth::user()->tenant_id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações Básicas')
                    ->schema([
                        Forms\Components\TextInput::make('sku')
                            ->label('SKU / Código')
                            ->required() 
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Material')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('category_id')
                            ->label('Categoria')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('ncm')
                            ->label('NCM')
                            ->maxLength(255),
                    ])->columns(2),

                Forms\Components\Section::make('Controle Financeiro e Estoque')
                    ->schema([
                        Forms\Components\TextInput::make('unit_cost')
                            ->label('Custo Unitário')
                            ->required()
                            ->numeric()
                            ->prefix('R$'),
                        Forms\Components\TextInput::make('price')
                            ->label('Preço de Venda')
                            ->required()
                            ->numeric()
                            ->default(0)
                            ->prefix('R$'),
                        Forms\Components\TextInput::make('current_stock')
                            ->label('Estoque Atual')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('min_stock')
                            ->label('Estoque Mínimo')
                            ->required()
                            ->numeric()
                            ->default(0),
                        Forms\Components\TextInput::make('max_stock')
                            ->label('Estoque Máximo')
                            ->required()
                            ->numeric()
                            ->default(0),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoria')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Custo')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Preço')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Qtd. Atual')
                    ->numeric()
                    ->sortable()
                    ->color(fn ($record) => $record->current_stock <= $record->min_stock ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('ncm')
                    ->label('NCM')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label('Filtrar por Categoria'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterials::route('/'),
            'create' => Pages\CreateMaterial::route('/create'),
            'edit' => Pages\EditMaterial::route('/{record}/edit'),
        ];
    }
}