<?php

namespace App\Filament\Exports;

use App\Models\PurchaseOrder;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PurchaseOrderExporter extends Exporter
{
    protected static ?string $model = PurchaseOrder::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')->label('Aberta em')->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i')),
            ExportColumn::make('supplier.name')->label('Fornecedor'),
            ExportColumn::make('total_value')->label('Valor Total'),
            ExportColumn::make('expected_delivery_date')->label('Previsão de Entrega')->formatStateUsing(fn ($state) => $state?->format('d/m/Y')),
            ExportColumn::make('status')->label('Status')->formatStateUsing(fn (string $state) => PurchaseOrder::statusOptions()[$state] ?? $state),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Sua exportação de ordens de compra foi concluída, '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).' exportados.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
