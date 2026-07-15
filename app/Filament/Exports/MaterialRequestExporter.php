<?php

namespace App\Filament\Exports;

use App\Models\MaterialRequest;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class MaterialRequestExporter extends Exporter
{
    protected static ?string $model = MaterialRequest::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')->label('Aberta em')->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i')),
            ExportColumn::make('user.name')->label('Solicitante'),
            ExportColumn::make('maintenanceOrder.os_number')->label('OS de Origem'),
            ExportColumn::make('status')->label('Status')->formatStateUsing(fn (string $state) => MaterialRequest::statusOptions()[$state] ?? $state),
            ExportColumn::make('approvedBy.name')->label('Aprovado por'),
            ExportColumn::make('approved_at')->label('Aprovado em')->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Sua exportação de requisições de compra foi concluída, '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).' exportados.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
