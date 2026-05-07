<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaterialCategoryResource\Pages;
use App\Models\MaterialCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MaterialCategoryResource extends Resource
{
    protected static ?string $model = MaterialCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-bookmark';
    protected static ?string $navigationGroup = 'GESTAO DE ESTOQUE';
    protected static ?string $navigationLabel = 'Categorias';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome da Categoria')
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaterialCategories::route('/'),
            'create' => Pages\CreateMaterialCategory::route('/create'),
            'edit' => Pages\EditMaterialCategory::route('/{record}/edit'),
        ];
    }
}