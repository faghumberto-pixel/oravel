<?php

namespace App\Filament\Resources;

use App\Filament\Attributes\BelongsToFeature;
use App\Filament\Resources\ChecklistGroupResource\Pages;
use App\Models\ChecklistGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

#[BelongsToFeature('checklists')]
class ChecklistGroupResource extends Resource
{
    protected static ?string $model = ChecklistGroup::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Ativos e Materiais';

    protected static ?string $navigationLabel = 'Grupos de Checklist';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nome do Grupo')
                ->placeholder('Ex: Geradores de Energia, Compressores de Ar, Caminhão Munck')
                ->required()
                ->maxLength(191),

            Forms\Components\Textarea::make('description')
                ->label('Objetivo')
                ->placeholder('Ex: Evitar falhas de partida e superaquecimento')
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Grupo')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('description')
                    ->label('Objetivo')
                    ->limit(60),
                Tables\Columns\TextColumn::make('assets_count')
                    ->label('Ativos Vinculados')
                    ->counts('assets'),
                Tables\Columns\TextColumn::make('template_items_count')
                    ->label('Itens do Checklist Básico')
                    ->counts('templateItems'),
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

    public static function getRelations(): array
    {
        return [
            ChecklistGroupResource\RelationManagers\TemplateItemsRelationManager::class,
        ];
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
