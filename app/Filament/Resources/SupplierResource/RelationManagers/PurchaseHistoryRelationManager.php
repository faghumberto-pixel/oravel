<?php

namespace App\Filament\Resources\SupplierResource\RelationManagers;

use App\Models\PurchaseOrder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Historico de compras -- somente leitura, PurchaseOrder e' gerenciada
 * de verdade em PurchaseOrderResource.
 */
class PurchaseHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'purchaseOrders';

    protected static ?string $title = 'Histórico de Compras';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Aberta em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_value')
                    ->label('Valor Total')
                    ->money('BRL'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn (string $state) => PurchaseOrder::statusOptions()[$state] ?? $state),
                Tables\Columns\TextColumn::make('expected_delivery_date')
                    ->label('Previsão')
                    ->date('d/m/Y')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (PurchaseOrder $record) => \App\Filament\Resources\PurchaseOrderResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([]);
    }
}
