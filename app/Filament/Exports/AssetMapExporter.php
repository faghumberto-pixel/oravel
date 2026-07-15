<?php

namespace App\Filament\Exports;

use App\Models\Asset;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AssetMapExporter extends Exporter
{
    protected static ?string $model = Asset::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('patrimonio')->label('Patrimônio'),
            ExportColumn::make('capacity_value')->label('Capacidade')
                ->getStateUsing(fn (Asset $record) => trim(($record->capacity_value ?? '').' '.($record->capacity_unit ?? ''))),
            ExportColumn::make('cliente_ou_unidade')->label('Cliente/Unidade')
                ->getStateUsing(fn (Asset $record) => $record->client
                    ? 'Cliente: '.$record->client->name
                    : ($record->internalUnit ? 'Unidade: '.$record->internalUnit->name : '—')),
            ExportColumn::make('horimetro_atual')->label('Horímetro'),
            ExportColumn::make('endereco')->label('Endereço'),
            ExportColumn::make('cep')->label('CEP'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Sua exportação do mapa de equipamentos foi concluída, '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).' exportados.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
