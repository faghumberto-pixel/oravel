<?php

namespace App\Filament\Resources\AssetResource\Widgets;

use App\Models\Asset;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AssetStats extends BaseWidget
{
    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $total = Asset::count();
        $disponivel = Asset::where('status', 'disponivel')->count();
        $manutencao = Asset::where('status', 'manutencao')->count();
        $emUso = Asset::whereIn('status', ['locado', 'operando'])->count();

        return [
            Stat::make('Total de Ativos', $total)
                ->description('Cadastrados na frota')
                ->color('gray'),

            Stat::make('Disponíveis', $disponivel)
                ->description('Prontos para despacho')
                ->color('success'),

            Stat::make('Em Manutenção', $manutencao)
                ->description('Parados para reparo')
                ->color($manutencao > 0 ? 'danger' : 'success'),

            Stat::make('Locados / Em Operação', $emUso)
                ->description('Gerando receita')
                ->color('warning'),
        ];
    }
}
