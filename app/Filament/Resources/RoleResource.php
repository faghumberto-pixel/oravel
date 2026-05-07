<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationGroup = 'GESTAO DE PESSOAS';
    protected static ?string $navigationLabel = 'Funções';
    protected static ?string $pluralModelLabel = 'Funções';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('Dados da Função')
                ->schema([
                    TextInput::make('name')
                        ->label('Nome da Função')
                        ->required()
                        ->unique(ignoreRecord: true),
                ]),

            Section::make('Permissões')
                ->description('Selecione as ações permitidas para esta função.')
                ->schema([
                    // A MÁGICA ESTÁ AQUI: O relationship gerencia o sync() automaticamente.
                    // O options() garante que o valor enviado seja o ID (int), resolvendo o erro 22P02.
                    CheckboxList::make('permissions')
                        ->relationship('permissions', 'name') // Usa o nome na busca
                        ->label('Lista de Permissões')
                        ->options(Permission::all()->pluck('name', 'id')) // Envia o ID para o banco
                        ->columns(3)
                        ->bulkToggleable()
                        ->searchable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->label('Nome')->searchable(),
            Tables\Columns\TextColumn::make('permissions_count')
                ->label('Permissões Ativas')
                ->counts('permissions')
                ->badge(),
            Tables\Columns\TextColumn::make('created_at')->label('Criado em')->dateTime('d/m/Y'),
        ])
        ->actions([
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}