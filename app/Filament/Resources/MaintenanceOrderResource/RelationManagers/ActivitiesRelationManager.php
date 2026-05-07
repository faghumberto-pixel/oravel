<?php

namespace App\Filament\Resources\MaintenanceOrderResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'activities'; // Garante que a relação seja reconhecida
    protected static ?string $title = 'Log de Auditoria';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('description')->label('Evento'),
                Tables\Columns\TextColumn::make('causer.name')->label('Usuário'),
                Tables\Columns\TextColumn::make('created_at')->label('Data/Hora')->dateTime(),
                Tables\Columns\TextColumn::make('properties')->label('Alterações')
                    ->formatStateUsing(fn ($state) => json_encode($state['attributes'] ?? ''))
                    ->limit(50),
            ])
            ->defaultSort('created_at', 'desc');
    }
}