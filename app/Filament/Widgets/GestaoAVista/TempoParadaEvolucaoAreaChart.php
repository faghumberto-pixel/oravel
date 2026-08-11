<?php

namespace App\Filament\Widgets\GestaoAVista;

use App\Filament\Widgets\Charts\AreaChart;
use App\Services\GestaoAVistaService;
use App\Support\Tenancy;

/**
 * Bloco de fechamento (rodapé, coluna esquerda): evolução do tempo de
 * parada não planejada (h) por mês, área preenchida. Usa AreaChart com
 * uma única série real (seriesB vazia, "Realizado vs Planejado" não se
 * aplica aqui -- só interessa a tendência de uma métrica).
 */
class TempoParadaEvolucaoAreaChart extends AreaChart
{
    public function mount(
        array $labels = [],
        array $seriesA = [],
        array $seriesB = [],
        ?string $chartTitle = null,
        ?string $sourceNote = null,
        array $seriesC = [],
        bool $empilhar = false,
        ?string $from = null,
        ?string $until = null,
        ?string $branchId = null,
        ?string $assetId = null,
    ): void {
        $tenant = Tenancy::current();

        if ($tenant) {
            $service = new GestaoAVistaService($tenant->id);
            $resultado = $service->tempoParadaNaoPlanejada([
                'from' => $from,
                'until' => $until,
                'branchId' => $branchId,
                'assetId' => $assetId,
            ]);

            $labels = $resultado['serie_mensal']['labels'];
            $seriesA = ['name' => 'Parada Não Planejada (h)', 'color' => '#e6534d', 'data' => $resultado['serie_mensal']['valores']];
        }

        parent::mount(
            labels: $labels,
            seriesA: $seriesA,
            seriesB: ['name' => '', 'color' => '#e6534d', 'data' => array_fill(0, count($labels), 0)],
            chartTitle: 'Evolução do Tempo de Parada Não Planejada',
        );
    }
}
