<?php

namespace App\Filament\Resources\AssetResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Itens extras de checklist especificos deste ativo (alem do basico do
 * Grupo), ex: pontos do manual do fabricante daquele equipamento em
 * particular. Somam ao checklist quando uma OS e gerada, sem alterar o
 * template do Grupo.
 */
class ChecklistItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'extraChecklistItems';

    protected static ?string $title = 'Itens Extras do Manual (deste ativo)';

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
