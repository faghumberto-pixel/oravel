<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasSuperAdminTenantColumn;
use App\Filament\Resources\CriticalityLevelResource\Pages;
use App\Models\CriticalityLevel;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CriticalityLevelResource extends Resource
{
    use HasSuperAdminTenantColumn;

    protected static ?string $model = CriticalityLevel::class;

    protected static ?string $modelLabel = 'Nível de Criticidade';

    protected static ?string $pluralModelLabel = 'Níveis de Criticidade';

    protected static ?string $navigationIcon = 'heroicon-o-fire';

    protected static ?string $navigationGroup = 'PCM';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Código')
                    ->helperText('Identificador curto usado na Matriz ABC (ex: A, B, C, D).')
                    ->required()
                    ->maxLength(10),
                Forms\Components\TextInput::make('name')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('Alta, Média, Baixa, Emergencial...'),
                Forms\Components\ColorPicker::make('color')
                    ->label('Cor')
                    ->required(),
                Forms\Components\Toggle::make('is_urgent')
                    ->label('Urgente')
                    ->helperText('Ativos/O.S. neste nível aparecem em vermelho no Kanban de Manutenção.')
                    ->default(false),
                Forms\Components\TextInput::make('sort_order')
                    ->label('Ordem')
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                static::tenantColumn(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color(fn (CriticalityLevel $record): string => $record->is_urgent ? 'danger' : 'gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ColorColumn::make('color')
                    ->label('Cor'),
                Tables\Columns\IconColumn::make('is_urgent')
                    ->label('Urgente')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordem')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->reorderable('sort_order')
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
            'index' => Pages\ListCriticalityLevels::route('/'),
            'create' => Pages\CreateCriticalityLevel::route('/create'),
            'edit' => Pages\EditCriticalityLevel::route('/{record}/edit'),
        ];
    }
}
