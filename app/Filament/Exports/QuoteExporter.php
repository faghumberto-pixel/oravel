<?php

namespace App\Filament\Exports;

use App\Models\Quote;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class QuoteExporter extends Exporter
{
    protected static ?string $model = Quote::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('client.name')->label('Cliente'),
            ExportColumn::make('type')
                ->label('Tipo')
                ->formatStateUsing(fn (string $state) => Quote::typeLabels()[$state] ?? $state),
            ExportColumn::make('status')
                ->label('Status')
                ->formatStateUsing(fn (string $state) => Quote::statusLabels()[$state] ?? $state),
            ExportColumn::make('total_value')->label('Valor Total'),
            ExportColumn::make('assignedUser.name')->label('Responsável'),
            ExportColumn::make('sent_at')->label('Enviado em'),
            ExportColumn::make('created_at')->label('Criado em'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Sua exportação de orçamentos foi concluída, '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).' exportados.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
