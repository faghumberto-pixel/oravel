<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ContactsRelationManager extends RelationManager
{
    protected static string $relationship = 'contacts';

    protected static ?string $title = 'Contatos';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome')
                ->required()
                ->maxLength(255),
            Forms\Components\TextInput::make('role_title')
                ->label('Cargo')
                ->maxLength(255),
            Forms\Components\TextInput::make('phone')
                ->label('Telefone')
                ->tel(),
            Forms\Components\TextInput::make('email')
                ->label('E-mail')
                ->email(),
            Forms\Components\Toggle::make('is_primary')
                ->label('Contato Principal'),
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
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
