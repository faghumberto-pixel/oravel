<?php

namespace App\Filament\Resources\MaintenanceOrderResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ChecklistRelationManager extends RelationManager
{
    protected static string $relationship = 'checklists';
    protected static ?string $title = 'Checklist de Inspeção';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Toggle::make('is_completed')
                ->label('Concluído/Aprovado'),
            Forms\Components\Textarea::make('notes')
                ->label('Observações Técnicas')
                ->rows(3),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('category')->label('Categoria'),
                Tables\Columns\TextColumn::make('item_name')->label('Item'),
                Tables\Columns\IconColumn::make('is_completed')->boolean()->label('Ok?'),
                Tables\Columns\TextColumn::make('notes')->label('Notas')->limit(30),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('Adicionar Item'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}