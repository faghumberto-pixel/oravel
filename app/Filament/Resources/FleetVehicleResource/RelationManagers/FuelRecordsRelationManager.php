<?php

namespace App\Filament\Resources\FleetVehicleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FuelRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'fuelRecords';

    protected static ?string $title = 'Abastecimentos';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('data')
                ->label('Data')
                ->required()
                ->default(now()),
            Forms\Components\TextInput::make('litros')
                ->label('Litros')
                ->numeric()
                ->required(),
            Forms\Components\TextInput::make('valor')
                ->label('Valor (R$)')
                ->numeric()
                ->prefix('R$')
                ->required(),
            Forms\Components\TextInput::make('km_atual')
                ->label('KM no Abastecimento')
                ->numeric()
                ->helperText('Atualiza o KM atual do veículo ao salvar.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('data')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('litros')->label('Litros'),
                Tables\Columns\TextColumn::make('valor')->label('Valor')->money('BRL'),
                Tables\Columns\TextColumn::make('km_atual')->label('KM'),
            ])
            ->defaultSort('data', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function ($record) {
                        if ($record->km_atual) {
                            $record->fleetVehicle->update(['km_atual' => max($record->km_atual, $record->fleetVehicle->km_atual)]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
