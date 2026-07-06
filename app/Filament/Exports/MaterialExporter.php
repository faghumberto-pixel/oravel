<?php

namespace App\Filament\Exports;

use App\Models\Material;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class MaterialExporter extends Exporter
{
    protected static ?string $model = Material::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('sku')->label('SKU'),
            ExportColumn::make('name')->label('Nome'),
            ExportColumn::make('category.name')->label('Categoria'),
            ExportColumn::make('supplier.name')->label('Fornecedor'),
            ExportColumn::make('price')->label('Preço'),
            ExportColumn::make('current_stock')->label('Estoque Atual'),
            ExportColumn::make('min_stock')->label('Estoque Mínimo'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Sua exportação de materiais foi concluída, '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).' exportados.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
