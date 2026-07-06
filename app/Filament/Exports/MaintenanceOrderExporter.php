<?php

namespace App\Filament\Exports;

use App\Models\MaintenanceOrder;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class MaintenanceOrderExporter extends Exporter
{
    protected static ?string $model = MaintenanceOrder::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('os_number')->label('Nº OS'),
            ExportColumn::make('asset.name')->label('Ativo'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('maintenance_type')->label('Tipo'),
            ExportColumn::make('technician.name')->label('Técnico'),
            ExportColumn::make('created_at')->label('Data')->formatStateUsing(fn ($state) => $state?->format('d/m/Y')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Sua exportação de ordens de serviço foi concluída, '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).' exportados.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
