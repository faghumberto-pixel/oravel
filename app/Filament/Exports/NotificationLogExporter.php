<?php

namespace App\Filament\Exports;

use App\Models\NotificationLog;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class NotificationLogExporter extends Exporter
{
    protected static ?string $model = NotificationLog::class;

    private const CATEGORIES = [
        'success' => 'Sucesso',
        'warning' => 'Aviso',
        'danger' => 'Erro',
        'info' => 'Informativo',
    ];

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')->label('Data/Hora')->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i:s')),
            ExportColumn::make('data.status')->label('Categoria')->formatStateUsing(fn (?string $state) => self::CATEGORIES[$state] ?? 'Informativo'),
            ExportColumn::make('data.title')->label('Título'),
            ExportColumn::make('notifiable.name')->label('Destinatário'),
            ExportColumn::make('notifiable.email')->label('E-mail'),
            ExportColumn::make('read_at')->label('Lida em')->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i:s')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Sua exportação de notificações foi concluída, '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).' exportados.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
