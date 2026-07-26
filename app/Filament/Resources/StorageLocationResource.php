<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSuperAdminTenantColumn;
use App\Filament\Resources\StorageLocationResource\Pages;
use App\Models\StorageLocation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;

class StorageLocationResource extends BaseResource
{
    use HasSuperAdminTenantColumn;

    protected static ?string $model = StorageLocation::class;

    protected static ?string $modelLabel = 'Localização';

    protected static ?string $pluralModelLabel = 'Localizações (Planta Baixa)';

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('internal_unit_id')
                    ->label('Unidade (Matriz/Filial)')
                    ->relationship('internalUnit', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('context')
                    ->label('Planta')
                    ->options(StorageLocation::contextOptions())
                    ->required(),

                Forms\Components\TextInput::make('code')
                    ->label('Código')
                    ->helperText('Ex: "A1-03" (corredor A1, prateleira 03) ou "Q12" (quadrante 12).')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('label')
                    ->label('Descrição')
                    ->maxLength(255),

                Forms\Components\TextInput::make('row')
                    ->label('Linha')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('column')
                    ->label('Coluna')
                    ->numeric()
                    ->required(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Ativa')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                static::tenantColumn(),
                Tables\Columns\TextColumn::make('internalUnit.name')
                    ->label('Unidade')
                    ->sortable(),
                Tables\Columns\TextColumn::make('context')
                    ->label('Planta')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => StorageLocation::contextOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->label('Descrição')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('row')
                    ->label('Linha')
                    ->sortable(),
                Tables\Columns\TextColumn::make('column')
                    ->label('Coluna')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativa')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('context')
                    ->label('Planta')
                    ->options(StorageLocation::contextOptions()),
                Tables\Filters\SelectFilter::make('internal_unit_id')
                    ->label('Unidade')
                    ->relationship('internalUnit', 'name'),
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
            'index' => Pages\ListStorageLocations::route('/'),
            'create' => Pages\CreateStorageLocation::route('/create'),
            'edit' => Pages\EditStorageLocation::route('/{record}/edit'),
        ];
    }
}
