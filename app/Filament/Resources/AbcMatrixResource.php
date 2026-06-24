<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbcMatrixResource\Pages;
use App\Models\AbcMatrix;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AbcMatrixResource extends Resource
{
    protected static ?string $model = AbcMatrix::class;

    protected static ?string $modelLabel = 'Matriz ABC';
    protected static ?string $pluralModelLabel = 'Matrizes ABC';
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'GESTÃO DE ATIVOS';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nivel')
                    ->label('Nível')
                    ->required()
                    ->maxLength(1)
                    ->placeholder('A, B ou C'),
                Forms\Components\TextInput::make('descricao')
                    ->label('Descrição')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Baixa, Media, Critica'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nivel')
                    ->label('Nível')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('descricao')
                    ->label('Descrição')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
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
            'index' => Pages\ListAbcMatrices::route('/'),
            'create' => Pages\CreateAbcMatrix::route('/create'),
            'edit' => Pages\EditAbcMatrix::route('/{record}/edit'),
        ];
    }
}
