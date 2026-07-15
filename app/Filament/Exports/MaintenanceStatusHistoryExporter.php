<?php

namespace App\Filament\Exports;

use App\Models\MaintenanceStatusHistory;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class MaintenanceStatusHistoryExporter extends Exporter
{
    protected static ?string $model = MaintenanceStatusHistory::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')->label('Data/Hora')->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i:s')),
            ExportColumn::make('maintenanceOrder.os_number')->label('OS'),
            ExportColumn::make('maintenanceOrder.asset.name')->label('Ativo'),
            ExportColumn::make('old_status')->label('De'),
            ExportColumn::make('new_status')->label('Para'),
            ExportColumn::make('observation')->label('Observação'),
            ExportColumn::make('user.name')->label('Responsável'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Sua exportação do histórico de status foi concluída, '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).' exportados.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
