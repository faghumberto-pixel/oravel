<?php

namespace App\Filament\Central\Resources\PmpEquipmentFamilyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Checklist técnico (seções + itens) da família -- ao importar
 * (MaintenanceOrderChecklist::importChecklistFromFamilyTemplate()), vira
 * MaintenanceOrderChecklist is_template=true no ChecklistGroup do
 * tenant, aparecendo em toda OS daquele grupo via
 * MaintenanceOrderChecklistSnapshotObserver.
 */
class ChecklistItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'checklistItems';

    protected static ?string $title = 'Checklist de Inspeção';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('section')
                ->label('Seção')
                ->placeholder('Ex: 1. Estrutural & Pneus')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('item_name')
                ->label('Item')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Forms\Components\Textarea::make('instructions')
                ->label('Instruções')
                ->rows(2)
                ->columnSpanFull(),

            Forms\Components\TextInput::make('sort_order')
                ->label('Ordem')
                ->numeric()
                ->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('item_name')
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('section')->label('Seção')->badge(),
                Tables\Columns\TextColumn::make('item_name')->label('Item')->wrap(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->reorderable('sort_order');
    }
}
