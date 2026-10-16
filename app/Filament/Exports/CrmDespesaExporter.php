<?php

namespace App\Filament\Exports;

use App\Models\CrmVisitaDespesa;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class CrmDespesaExporter extends Exporter
{
    protected static ?string $model = CrmVisitaDespesa::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('data')->label('Data'),
            ExportColumn::make('vendedor.name')->label('Vendedor'),
            ExportColumn::make('tipo')->label('Tipo')->formatStateUsing(fn ($state) => CrmVisitaDespesa::tipoLabels()[$state] ?? $state),
            ExportColumn::make('valor')->label('Valor (R$)'),
            ExportColumn::make('descricao')->label('Descrição'),
            ExportColumn::make('litros')->label('Litros'),
            ExportColumn::make('veiculo.placa')->label('Veículo'),
            ExportColumn::make('visita.agendada_para')->label('Visita'),
            ExportColumn::make('status')->label('Status')->formatStateUsing(fn ($state) => CrmVisitaDespesa::statusLabels()[$state] ?? $state),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Exportação de despesas concluída: '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).'.';
    }
}
