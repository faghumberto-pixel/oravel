<?php

namespace App\Filament\Central\Widgets;

use App\Models\DatabaseBackup;
use App\Models\Tenant;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/** Resumo no topo de "Backups do Banco": quais clientes estão protegidos hoje e quais não. */
class BackupStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $recent = now()->subHours(26); // backup diário às 03:00; folga de 2 h
        $tenants = Tenant::withoutGlobalScopes()->orderBy('name')->get(['id', 'name']);

        $protectedIds = DatabaseBackup::where('kind', DatabaseBackup::KIND_TENANT)
            ->where('status', DatabaseBackup::STATUS_COMPLETED)->where('created_at', '>=', $recent)
            ->pluck('tenant_id')->unique()->all();

        $missing = $tenants->reject(fn ($t) => in_array($t->id, $protectedIds, true))->pluck('name');
        $platform = DatabaseBackup::where('kind', DatabaseBackup::KIND_PLATFORM)->where('status', DatabaseBackup::STATUS_COMPLETED)->latest()->first();
        $bytes = (int) DatabaseBackup::whereIn('kind', [DatabaseBackup::KIND_TENANT, DatabaseBackup::KIND_PLATFORM])->sum('size_bytes');

        return [
            Stat::make('Clientes com backup recente', $tenants->count() - $missing->count().' de '.$tenants->count())
                ->description('Concluído nas últimas 26 horas')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($missing->isEmpty() ? 'success' : 'warning'),
            Stat::make('Sem backup recente', $missing->count())
                ->description($missing->isEmpty() ? 'Todos os clientes estão protegidos' : $missing->take(4)->implode(', ').($missing->count() > 4 ? ' e mais '.($missing->count() - 4) : ''))
                ->descriptionIcon($missing->isEmpty() ? 'heroicon-m-check-circle' : 'heroicon-m-exclamation-triangle')
                ->color($missing->isEmpty() ? 'success' : 'danger'),
            Stat::make('Dados da plataforma', $platform ? $platform->created_at->format('d/m H:i') : 'nunca')
                ->description('Último backup do que não é de nenhum cliente')
                ->descriptionIcon('heroicon-m-server-stack')
                ->color($platform ? 'info' : 'gray'),
            Stat::make('Espaço usado', number_format($bytes / 1048576, 1, ',', '.').' MB')
                ->description('Backups por cliente e da plataforma (30 dias)')
                ->descriptionIcon('heroicon-m-circle-stack')
                ->color('gray'),
        ];
    }
}
