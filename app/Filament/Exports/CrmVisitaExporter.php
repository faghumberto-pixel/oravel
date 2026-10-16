<?php

namespace App\Filament\Exports;

use App\Models\CrmVisita;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class CrmVisitaExporter extends Exporter
{
    protected static ?string $model = CrmVisita::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('agendada_para')->label('Data e hora'),
            ExportColumn::make('vendedor.name')->label('Vendedor'),
            ExportColumn::make('alvo')->label('Visita a')->state(fn (CrmVisita $r) => $r->alvoNome()),
            ExportColumn::make('objetivo')->label('Objetivo')->formatStateUsing(fn ($state) => CrmVisita::objetivoLabels()[$state] ?? $state),
            ExportColumn::make('status')->label('Status')->formatStateUsing(fn ($state) => CrmVisita::statusLabels()[$state] ?? $state),
            ExportColumn::make('endereco')->label('Endereço'),
            ExportColumn::make('cidade')->label('Cidade'),
            ExportColumn::make('uf')->label('UF'),
            ExportColumn::make('veiculo.placa')->label('Veículo'),
            ExportColumn::make('km_inicio')->label('Km inicial'),
            ExportColumn::make('km_fim')->label('Km final'),
            ExportColumn::make('km_rodados')->label('Km rodados')->state(fn (CrmVisita $r) => $r->kmRodados()),
            ExportColumn::make('checkin_em')->label('Check-in'),
            ExportColumn::make('checkout_em')->label('Check-out'),
            ExportColumn::make('checkin_distancia_m')->label('Distância no check-in (m)'),
            ExportColumn::make('resultado')->label('Resultado'),
            ExportColumn::make('proximo_passo')->label('Próximo passo'),
            ExportColumn::make('proximo_contato')->label('Próximo contato'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Exportação de visitas concluída: '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).'.';
    }
}
