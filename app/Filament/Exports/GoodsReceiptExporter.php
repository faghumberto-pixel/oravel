<?php

namespace App\Filament\Exports;

use App\Models\GoodsReceipt;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class GoodsReceiptExporter extends Exporter
{
    protected static ?string $model = GoodsReceipt::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('received_at')->label('Recebido em')->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i')),
            ExportColumn::make('purchaseOrder.supplier.name')->label('Fornecedor'),
            ExportColumn::make('invoice_number')->label('Nota Fiscal'),
            ExportColumn::make('receivedBy.name')->label('Recebido por'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Sua exportação de recebimentos foi concluída, '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).' exportados.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
