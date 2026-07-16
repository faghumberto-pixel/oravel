<?php

namespace App\Filament\Resources\CrmLeadResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Historico de troca de vendedor -- registrado automaticamente por
 * CrmLeadObserver, nunca criado/editado pela UI (sem headerActions/
 * actions de escrita).
 */
class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    protected static ?string $title = 'Histórico de Atribuição';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->label('Data')->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('fromUser.name')->label('De')->placeholder('— (primeira atribuição)'),
                Tables\Columns\TextColumn::make('toUser.name')->label('Para'),
                Tables\Columns\TextColumn::make('changedBy.name')->label('Alterado por'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
