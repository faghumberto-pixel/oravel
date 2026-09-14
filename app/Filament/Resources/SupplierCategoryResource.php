<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierCategoryResource\Pages;
use App\Models\SupplierCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class SupplierCategoryResource extends BaseResource
{
    protected static ?string $model = SupplierCategory::class;

    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Compras';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Categoria de Fornecedor';

    protected static ?string $pluralModelLabel = 'Categorias de Fornecedor';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome da Categoria')
                ->placeholder('Peças, Combustível, Serviços Terceirizados...')
                ->required()
                ->maxLength(255),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Categoria')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('suppliers_count')
                    ->label('Fornecedores')
                    ->counts('suppliers'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupplierCategories::route('/'),
            'create' => Pages\CreateSupplierCategory::route('/create'),
            'edit' => Pages\EditSupplierCategory::route('/{record}/edit'),
        ];
    }
}
