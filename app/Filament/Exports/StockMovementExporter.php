<?php

namespace App\Filament\Exports;

use App\Models\StockMovement;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class StockMovementExporter extends Exporter
{
    protected static ?string $model = StockMovement::class;

    private const TYPES = [
        StockMovement::TYPE_ENTRADA_COMPRA => 'Entrada (Compra)',
        StockMovement::TYPE_SAIDA_CONSUMO => 'Saída (Consumo)',
        StockMovement::TYPE_AJUSTE_MANUAL => 'Ajuste (Inventário)',
    ];

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')->label('Data/Hora')->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i')),
            ExportColumn::make('material.sku')->label('SKU'),
            ExportColumn::make('material.name')->label('Material'),
            ExportColumn::make('type')->label('Tipo')->formatStateUsing(fn (string $state) => self::TYPES[$state] ?? $state),
            ExportColumn::make('quantity')->label('Quantidade'),
            ExportColumn::make('balance_after')->label('Saldo Após'),
            ExportColumn::make('createdBy.name')->label('Registrado por'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Sua exportação do histórico de estoque foi concluída, '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).' exportados.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
