<?php

namespace App\Filament\Resources\FleetDriverResource\RelationManagers;

use App\Models\EquipmentMovement;
use Carbon\CarbonInterface;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Historico de movimentacoes realizadas por este motorista -- so leitura,
 * sem criar/editar/apagar aqui (a atribuicao acontece no checklist mobile
 * da propria movimentacao).
 */
class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'equipmentMovements';

    protected static ?string $title = 'Histórico de Movimentações';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('asset.name')->label('Ativo'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === EquipmentMovement::TYPE_MOBILIZACAO ? 'Mobilização' : 'Desmobilização'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        EquipmentMovement::STATUS_CONCLUIDO => 'success',
                        EquipmentMovement::STATUS_AGUARDANDO_APROVACAO => 'warning',
                        EquipmentMovement::STATUS_EM_ANDAMENTO => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('started_at')->label('Início')->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('completed_at')->label('Conclusão')->dateTime('d/m/Y H:i'),
                Tables\Columns\TextColumn::make('duracao')
                    ->label('Duração')
                    ->state(function (EquipmentMovement $record) {
                        if (! $record->started_at || ! $record->completed_at) {
                            return '—';
                        }

                        return $record->started_at->diffForHumans($record->completed_at, ['parts' => 2, 'syntax' => CarbonInterface::DIFF_ABSOLUTE]);
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
