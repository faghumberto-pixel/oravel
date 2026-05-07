<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChecklistGroupResource\Pages;
use App\Models\ChecklistGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ChecklistGroupResource extends Resource
{
    // Define o model corretamente
    protected static ?string $model = ChecklistGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';
    protected static ?string $navigationLabel = 'Grupos de Ativos';
    protected static ?string $modelLabel = 'Grupo de Ativos';
    protected static ?string $pluralModelLabel = 'Grupos de Ativos';
    protected static ?string $navigationGroup = 'GESTAO DE ATIVOS';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados do Centro de Custo')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Grupo (Ex: Geradores)')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\Hidden::make('tenant_id')
                            ->default(fn () => Auth::user()->tenant_id),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome do Grupo')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChecklistGroups::route('/'),
            'create' => Pages\CreateChecklistGroup::route('/create'),
            'edit' => Pages\EditChecklistGroup::route('/{record}/edit'),
        ];
    }
}