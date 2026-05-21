<?php

namespace App\Filament\Resources;

use App\Models\Category;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->label('Nome da Categoria')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    /**
     * REMOVIDO O GETPAGES: 
     * Sem o mapeamento de páginas inexistentes, o Laravel ignora o carregamento 
     * de rotas deste recurso e elimina qualquer erro de Fatal Error no boot.
     */
}