<?php

namespace App\Filament\Resources\FleetVehicleResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TollRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'tollRecords';

    protected static ?string $title = 'Pedágio (Sem Parar)';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('data')
                ->label('Data')
                ->required()
                ->default(now()),
            Forms\Components\TextInput::make('valor')
                ->label('Valor (R$)')
                ->numeric()
                ->prefix('R$')
                ->required(),
            Forms\Components\TextInput::make('praca_pedagio')
                ->label('Praça de Pedágio'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('praca_pedagio')
            ->columns([
                Tables\Columns\TextColumn::make('data')->label('Data')->date('d/m/Y')->sortable(),
                Tables\Columns\TextColumn::make('praca_pedagio')->label('Praça'),
                Tables\Columns\TextColumn::make('valor')->label('Valor')->money('BRL'),
                Tables\Columns\TextColumn::make('freightRecord.origem')->label('Viagem (origem)'),
            ])
            ->defaultSort('data', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
