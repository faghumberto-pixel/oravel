<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AssetCategoryResource\Pages;
use App\Models\AssetCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class AssetCategoryResource extends Resource
{
    protected static ?string $model = AssetCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationGroup = 'GESTÃO DE ATIVOS';
    protected static ?string $navigationLabel = 'Categorias de Ativos';
    protected static ?string $pluralModelLabel = 'Categorias de Ativos';
    protected static ?string $slug = 'asset-categories';

    // 🚀 AJUSTE DO ERRO DA IMAGEM: Vincula ao relacionamento no plural pertencente ao modelo Tenant
    protected static ?string $tenantRelationshipName = 'assetCategories';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Cadastro de Categoria de Ativos / Equipamentos')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome da Categoria')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (string $operation, $state, Forms\Set $set) => 
                            $operation === 'create' ? $set('slug', Str::slug($state)) : null
                        ),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug / URL Amigável')
                        ->disabled()
                        ->dehydrated()
                        ->required()
                        ->maxLength(255),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Cadastrado em')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // 🚀 FILTRO REAL DO BANCO: Garante que os filtros de busca superiores da listagem 
                // leiam estritamente as linhas físicas que existem na tabela asset_categories do Tenant.
                Tables\Filters\SelectFilter::make('id')
                    ->label('Filtrar Categoria')
                    ->options(fn () => \App\Models\AssetCategory::pluck('name', 'id')->toArray()),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssetCategories::route('/'),
            'create' => Pages\CreateAssetCategory::route('/create'),
            'edit' => Pages\EditAssetCategory::route('/{record}/edit'),
        ];
    }
}