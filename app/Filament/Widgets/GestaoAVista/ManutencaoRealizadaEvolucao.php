<?php

namespace App\Filament\Widgets\GestaoAVista;

use App\Filament\Widgets\Charts\AreaChart;
use App\Services\GestaoAVistaService;
use App\Support\Tenancy;

/**
 * Gráfico de evolução mensal da Coluna 1: Planejado vs. Realizado (contagem
 * de OS). Mesmo padrão de mount() com filtros extras de
 * ManutencaoRealizadaGauge, agora sobre AreaChart.
 */
class ManutencaoRealizadaEvolucao extends AreaChart
{
    public function mount(
        array $labels = [],
        array $seriesA = [],
        array $seriesB = [],
        ?string $chartTitle = null,
        ?string $sourceNote = null,
        ?string $from = null,
        ?string $until = null,
        ?string $branchId = null,
        ?string $assetId = null,
    ): void {
        $tenant = Tenancy::current();

        if ($tenant) {
            $service = new GestaoAVistaService($tenant->id);
            $resultado = $service->percentualManutencaoRealizada([
                'from' => $from,
                'until' => $until,
                'branchId' => $branchId,
                'assetId' => $assetId,
            ]);

            $labels = $resultado['serie_mensal']['labels'];
            $seriesA = ['name' => 'Planejado', 'color' => '#94a3b8', 'data' => $resultado['serie_mensal']['planejado']];
            $seriesB = ['name' => 'Realizado', 'color' => '#199e70', 'data' => $resultado['serie_mensal']['realizado']];
        }

        parent::mount(
            labels: $labels,
            seriesA: $seriesA,
            seriesB: $seriesB,
        );
    }
}
