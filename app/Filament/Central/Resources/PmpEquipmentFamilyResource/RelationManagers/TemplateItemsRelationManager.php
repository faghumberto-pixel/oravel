<?php

namespace App\Filament\Central\Resources\PmpEquipmentFamilyResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Itens de manutenção da família -- 1 linha vira 1 MaintenancePlan quando
 * um tenant importa (MaintenancePlan::importFromFamilyTemplate()).
 */
class TemplateItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'templateItems';

    protected static ?string $title = 'Itens de Manutenção';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Item')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),

            Forms\Components\Select::make('periodicity_label')
                ->label('Periodicidade')
                ->options([
                    'Diária' => 'Diária',
                    'Semanal' => 'Semanal',
                    '250-300h / Mensal' => '250-300h / Mensal',
                    '500-600h / Trimestral' => '500-600h / Trimestral',
                    '1000-2000h / Anual' => '1000-2000h / Anual',
                ])
                ->required(),

            Forms\Components\TextInput::make('interval_hours')
                ->label('Intervalo (horas)')
                ->numeric()
                ->minValue(1),

            Forms\Components\TextInput::make('interval_days')
                ->label('Intervalo (dias)')
                ->numeric()
                ->minValue(1),

            Forms\Components\Toggle::make('is_critical')
                ->label('Item crítico')
                ->helperText('Item crítico vencido bloqueia locação do equipamento automaticamente.'),

            Forms\Components\Textarea::make('notes')
                ->label('Observações')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Item')->wrap(),
                Tables\Columns\TextColumn::make('periodicity_label')->label('Periodicidade')->badge(),
                Tables\Columns\IconColumn::make('is_critical')->label('Crítico')->boolean(),
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
