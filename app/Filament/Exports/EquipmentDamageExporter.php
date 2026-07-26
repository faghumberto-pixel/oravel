<?php

namespace App\Filament\Exports;

use App\Models\EquipmentDamage;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class EquipmentDamageExporter extends Exporter
{
    protected static ?string $model = EquipmentDamage::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('maintenanceOrder.os_number')->label('OS'),
            ExportColumn::make('asset.patrimonio')->label('Patrimônio'),
            ExportColumn::make('asset.name')->label('Ativo'),
            ExportColumn::make('severity')->label('Severidade'),
            ExportColumn::make('damage_type')
                ->label('Tipo de Dano')
                ->formatStateUsing(fn (?string $state) => EquipmentDamage::damageTypeLabels()[$state] ?? 'Não classificado'),
            ExportColumn::make('cause')
                ->label('Causa')
                ->formatStateUsing(fn (?string $state) => EquipmentDamage::causeLabels()[$state] ?? 'Não classificado'),
            ExportColumn::make('status')->label('Status'),
            ExportColumn::make('created_at')->label('Data'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Sua exportação de avarias foi concluída, '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).' exportados.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
