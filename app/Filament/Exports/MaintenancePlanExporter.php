<?php

namespace App\Filament\Exports;

use App\Models\MaintenancePlan;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class MaintenancePlanExporter extends Exporter
{
    protected static ?string $model = MaintenancePlan::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')->label('Item do Plano'),
            ExportColumn::make('asset_or_group')->label('Ativo / Grupo')
                ->getStateUsing(fn (MaintenancePlan $record) => $record->asset?->name ?? $record->checklistGroup?->name.' (grupo)'),
            ExportColumn::make('interval_hours')->label('Intervalo (horas)'),
            ExportColumn::make('notes')->label('Observação'),
            ExportColumn::make('is_active')->label('Ativo?')->formatStateUsing(fn ($state) => $state ? 'Sim' : 'Não'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Sua exportação de planos preventivos foi concluída, '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).' exportados.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
