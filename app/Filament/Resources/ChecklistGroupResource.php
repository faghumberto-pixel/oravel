<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChecklistGroupResource\Pages;
use App\Models\ChecklistGroup;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class ChecklistGroupResource extends Resource
{
    protected static ?string $model = ChecklistGroup::class;


    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = 'Configurações';

    protected static ?string $navigationLabel = 'Grupos de Checklist';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Defina os campos do seu formulário aqui quando necessário
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Defina as colunas da sua tabela aqui quando necessário
            ])
            ->filters([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
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
