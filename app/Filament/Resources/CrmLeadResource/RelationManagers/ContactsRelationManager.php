<?php

namespace App\Filament\Resources\CrmLeadResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Multiplas pessoas de contato dentro do prospect (empresa) -- diferente
 * de InteractionsRelationManager (log, so' Create+View), aqui faz
 * sentido editar um contato depois de cadastrado (cargo/telefone mudam).
 */
class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Contatos';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome')
                ->required(),
            Forms\Components\TextInput::make('role_title')
                ->label('Cargo')
                ->placeholder('Diretor, Gerente de Compras...'),
            Forms\Components\TextInput::make('phone')
                ->label('Telefone')
                ->tel(),
            Forms\Components\TextInput::make('email')
                ->label('E-mail')
                ->email(),
            Forms\Components\Toggle::make('is_primary')
                ->label('Contato Principal')
                ->helperText('Usado como contato do Cliente quando este prospect for convertido.'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome'),
                Tables\Columns\TextColumn::make('role_title')->label('Cargo')->placeholder('—'),
                Tables\Columns\TextColumn::make('phone')->label('Telefone')->placeholder('—'),
                Tables\Columns\TextColumn::make('email')->label('E-mail')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_primary')->label('Principal')->boolean(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Adicionar Contato'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
