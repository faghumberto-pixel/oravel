<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RadarOperacional extends BaseWidget
{
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        $tenant = Tenancy::current();

        if (! $tenant) {
            return [
                Stat::make('Disponível', 0),
                Stat::make('Locado', 0),
                Stat::make('Em Operação', 0),
                Stat::make('Em Manutenção', 0),
            ];
        }

        // Vocabulario oficial do formulario (AssetResource):
        // disponivel | locado | operando | manutencao
        // Asset ja e isolado por tenant via global scope.
        $disponivel = Asset::where('status', 'disponivel')->count();
        $locado = Asset::where('status', 'locado')->count();
        $operando = Asset::where('status', 'operando')->count();
        $emManutencao = Asset::where('status', 'manutencao')->count();

        // Nao existe historico diario de status de Asset (sem tabela de
        // snapshot) -- usamos volume de OS criadas por dia como um proxy
        // real de atividade nos 4 cards, em vez de inventar tendencia por
        // status especifico.
        $atividade = $this->getDailyOrderVolume();

        return [
            Stat::make('Disponível', $disponivel)
                ->description('Prontos para locação')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success')
                ->chart($atividade),

            Stat::make('Locado', $locado)
                ->description('Em contrato com cliente')
                ->descriptionIcon('heroicon-o-building-office-2')
                ->color('info')
                ->chart($atividade),

            Stat::make('Em Operação', $operando)
                ->description('Equipamentos em uso')
                ->descriptionIcon('heroicon-o-bolt')
                ->color('warning')
                ->chart($atividade),

            Stat::make('Em Manutenção', $emManutencao)
                ->description('Parados para manutenção')
                ->descriptionIcon('heroicon-o-wrench-screwdriver')
                ->color('danger')
                ->chart($atividade),
        ];
    }

    /**
     * Volume de OS criadas por dia, ultimos 30 dias (com zero nos dias sem
     * OS) -- serve como sparkline de "atividade" nos 4 cards acima, ja que
     * nao existe historico diario de status de Asset para uma tendencia
     * literal por status.
     */
    private function getDailyOrderVolume(): array
    {
        $counts = MaintenanceOrder::selectRaw("to_char(created_at, 'YYYY-MM-DD') as day, count(*) as total")
            ->where('created_at', '>=', now()->subDays(29)->startOfDay())
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->format('Y-m-d');
            $series[] = (int) ($counts[$day] ?? 0);
        }

        return $series;
    }
}
