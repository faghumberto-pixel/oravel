<?php

namespace App\Filament\Exports;

use App\Models\AccountPayable;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AccountPayableExporter extends Exporter
{
    protected static ?string $model = AccountPayable::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('description')->label('Descrição'),
            ExportColumn::make('branch.name')->label('Filial'),
            ExportColumn::make('amount')->label('Valor'),
            ExportColumn::make('due_date')->label('Vencimento'),
            ExportColumn::make('payment_date')->label('Pagamento'),
            ExportColumn::make('status')->label('Status')->formatStateUsing(fn ($state) => ucfirst($state)),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Sua exportação de contas a pagar foi concluída, '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).' exportados.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
