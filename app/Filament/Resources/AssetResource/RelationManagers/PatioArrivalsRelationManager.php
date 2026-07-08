<?php

namespace App\Filament\Resources\AssetResource\RelationManagers;

use Carbon\CarbonInterface;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Historico permanente de idas e vindas do patio deste ativo -- so'
 * leitura (a confirmacao acontece em App\Filament\Pages\PatioChegadas).
 * Util pra gestao operacional e eventual disputa com cliente sobre prazo
 * de devolucao.
 */
class PatioArrivalsRelationManager extends RelationManager
{
    protected static string $relationship = 'patioArrivals';

    protected static ?string $title = 'Histórico de Pátio';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('equipmentMovement.maintenanceOrder.client.name')
                    ->label('Cliente')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('equipmentMovement.completed_at')
                    ->label('Saiu (checklist concluído)')
                    ->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('arrived_at')
                    ->label('Chegada Confirmada')
                    ->dateTime('d/m/Y H:i')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('duracao_fora')
                    ->label('Tempo Fora')
                    ->state(function ($record) {
                        $saida = $record->equipmentMovement?->completed_at;
                        if (! $saida) {
                            return '—';
                        }

                        return $saida->diffForHumans($record->arrived_at, ['parts' => 2, 'syntax' => CarbonInterface::DIFF_ABSOLUTE]);
                    }),
                Tables\Columns\TextColumn::make('confirmedBy.name')->label('Confirmado por'),
                Tables\Columns\TextColumn::make('initial_condition_notes')->label('Condição de Entrada')->limit(50),
            ])
            ->defaultSort('arrived_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
