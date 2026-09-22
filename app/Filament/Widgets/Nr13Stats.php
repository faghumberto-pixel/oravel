<?php

namespace App\Filament\Widgets;

use App\Models\Nr13Document;
use App\Models\Nr13Inspection;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Cards de contagem (Válidos / A vencer / Vencidos) cruzando documentos e inspeções NR-13 --
 * mesmo padrão de App\Filament\Resources\AssetResource\Widgets\AssetStats. Cards de contagem,
 * não um kanban de arrastar (MaintenanceKanban): aqui o status é calculado pela data, não
 * movido manualmente por alguém.
 */
class Nr13Stats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $documentos = Nr13Document::whereNotNull('data_validade')->get();
        $inspecoes = Nr13Inspection::whereNotNull('data_proxima_inspecao')->get();

        $vencidos = $documentos->filter->isVencido()->count() + $inspecoes->filter->isVencida()->count();
        $aVencer = $documentos->filter->isProximoVencimento()->count() + $inspecoes->filter->isProximaDoVencimento()->count();
        $validos = ($documentos->count() + $inspecoes->count()) - $vencidos - $aVencer;

        return [
            Stat::make('Válidos', $validos)
                ->description('Documentos e inspeções em dia')
                ->color('success'),

            Stat::make('A vencer', $aVencer)
                ->description('Próximos 30 dias')
                ->color($aVencer > 0 ? 'warning' : 'success'),

            Stat::make('Vencidos', $vencidos)
                ->description('Documentos e inspeções vencidos')
                ->color($vencidos > 0 ? 'danger' : 'success'),
        ];
    }
}
