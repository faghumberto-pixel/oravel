<?php

namespace App\Filament\Resources\ChecklistGroupResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Checklist basico do Grupo: o "ponto de partida" copiado (snapshot) toda
 * vez que uma OS e gerada para um ativo deste Grupo. Editar aqui muda o
 * template para novas OS, sem afetar checklists ja criados (is_template=true
 * nao tem maintenance_order_id).
 */
class TemplateItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'templateItems';

    protected static ?string $title = 'Checklist Básico do Grupo';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('item_name')
                ->label('Item')
                ->required(),
            Forms\Components\TextInput::make('category')
                ->label('Categoria/Seção'),
            Forms\Components\Textarea::make('instructions')
                ->label('Instruções')
                ->rows(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('item_name')->label('Item'),
                Tables\Columns\TextColumn::make('category')->label('Categoria/Seção'),
                Tables\Columns\TextColumn::make('instructions')->label('Instruções')->limit(50),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['is_template'] = true;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
