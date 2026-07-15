<?php

namespace App\Filament\Exports;

use App\Models\ActivityLogEntry;
use App\Models\Asset;
use App\Models\CrmLead;
use App\Models\EquipmentDamage;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Str;

class ActivityLogExporter extends Exporter
{
    protected static ?string $model = ActivityLogEntry::class;

    private const SUBJECT_TYPES = [
        Asset::class => 'Ativo',
        EquipmentDamage::class => 'Avaria',
        CrmLead::class => 'Lead (CRM)',
    ];

    private const EVENTS = [
        'created' => 'Criado',
        'updated' => 'Editado',
        'deleted' => 'Excluído',
    ];

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')->label('Data/Hora')->formatStateUsing(fn ($state) => $state?->format('d/m/Y H:i:s')),
            ExportColumn::make('subject_type')->label('Modelo')->formatStateUsing(fn (?string $state) => self::SUBJECT_TYPES[$state] ?? Str::afterLast((string) $state, '\\')),
            ExportColumn::make('event')->label('Evento')->formatStateUsing(fn (?string $state) => self::EVENTS[$state] ?? $state),
            ExportColumn::make('causer.name')->label('Quem Fez'),
            ExportColumn::make('mudancas')->label('O Que Mudou')->getStateUsing(fn (ActivityLogEntry $record) => self::formatChanges($record)),
        ];
    }

    private static function formatChanges(ActivityLogEntry $record): string
    {
        $properties = $record->properties ?? collect();
        $attributes = collect($properties->get('attributes', []));
        $old = collect($properties->get('old', []));

        if ($record->event === 'updated' && $old->isNotEmpty()) {
            return $attributes
                ->map(fn ($value, $key) => "{$key}: ".self::formatValue($old->get($key)).' → '.self::formatValue($value))
                ->implode('; ');
        }

        if ($record->event === 'deleted') {
            return $old->map(fn ($value, $key) => "{$key}: ".self::formatValue($value))->implode('; ');
        }

        return $attributes->map(fn ($value, $key) => "{$key}: ".self::formatValue($value))->implode('; ');
    }

    private static function formatValue(mixed $value): string
    {
        if (is_null($value)) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Sim' : 'Não';
        }

        return (string) $value;
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Sua exportação do log de alterações foi concluída, '.number_format($export->successful_rows).' '.str('registro')->plural($export->successful_rows).' exportados.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('linha')->plural($failedRowsCount).' falharam ao exportar.';
        }

        return $body;
    }
}
