<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChecklistTemplateResource\Pages;
use App\Models\MaintenanceOrderChecklist;
use App\Models\ChecklistGroup;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChecklistTemplateResource extends Resource
{
    protected static ?string $model = MaintenanceOrderChecklist::class;
    protected static ?string $navigationLabel = 'Gestão de Checklists (Modelos)';
    protected static ?string $navigationIcon = 'heroicon-o-document-check';
    protected static ?string $navigationGroup = 'GESTAO DE MANUTENÇAO';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_template', true);
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Ajustado para usar ->options() e evitar o erro getResults() on null
            Forms\Components\Select::make('checklist_group_id')
                ->label('Grupo de Ativos')
                ->options(ChecklistGroup::all()->pluck('name', 'id'))
                ->required()
                ->searchable()
                ->preload(),

            Forms\Components\TextInput::make('category')
                ->label('Categoria')
                ->required(),

            Forms\Components\TextInput::make('section')
                ->label('Etapa')
                ->required(),

            Forms\Components\TextInput::make('item_name')
                ->label('Item')
                ->required(),

            Forms\Components\Hidden::make('is_template')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('group.name')
                ->label('Grupo')
                ->sortable(),
            Tables\Columns\TextColumn::make('category')
                ->label('Categoria'),
            Tables\Columns\TextColumn::make('section')
                ->label('Etapa'),
            Tables\Columns\TextColumn::make('item_name')
                ->label('Item'),
        ])
        ->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\EditAction::make(),
            Tables\Actions\DeleteAction::make(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChecklistTemplates::route('/'),
            'create' => Pages\CreateChecklistTemplate::route('/create'),
            'edit' => Pages\EditChecklistTemplate::route('/{record}/edit'),
        ];
    }
}