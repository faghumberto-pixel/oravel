<?php

namespace App\Filament\Resources\AssetCategoryResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * De-para configuravel entre categoria de ativo e norma exigida do
 * operador -- fonte real que a trigger de equipment_allocations consulta
 * pra decidir bloqueio (ver EquipmentAllocationResource).
 */
class NrRequirementsRelationManager extends RelationManager
{
    protected static string $relationship = 'nrRequirements';

    protected static ?string $title = 'Normas (NR) Exigidas do Operador';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('norma')
                ->label('Norma Regulamentadora')
                ->placeholder('Ex: NR-11, NR-12, NR-35')
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('norma')
            ->columns([
                Tables\Columns\TextColumn::make('norma')->label('Norma')->badge(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
