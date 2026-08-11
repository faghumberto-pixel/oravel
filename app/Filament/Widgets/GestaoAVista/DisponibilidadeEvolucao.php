<?php

namespace App\Filament\Widgets\GestaoAVista;

use App\Filament\Widgets\Charts\LineChartWithMarkers;
use App\Services\GestaoAVistaService;
use App\Support\Tenancy;

/**
 * Gráfico de evolução mensal da Coluna 2: % Disponibilidade dos
 * Equipamentos. Mesmo padrão de mount() com filtros extras.
 */
class DisponibilidadeEvolucao extends LineChartWithMarkers
{
    public function mount(
        array $labels = [],
        array $series = [],
        ?string $chartTitle = null,
        ?string $sourceNote = null,
        string $markerStyle = 'circle',
        ?string $from = null,
        ?string $until = null,
        ?string $branchId = null,
        ?string $assetId = null,
    ): void {
        $tenant = Tenancy::current();

        if ($tenant) {
            $service = new GestaoAVistaService($tenant->id);
            $resultado = $service->disponibilidadeEquipamentos([
                'from' => $from,
                'until' => $until,
                'branchId' => $branchId,
                'assetId' => $assetId,
            ]);

            $labels = $resultado['serie_mensal']['labels'];
            $series = [['name' => 'Disponibilidade', 'color' => '#3987e5', 'data' => $resultado['serie_mensal']['valores']]];
        }

        parent::mount(labels: $labels, series: $series);
    }
}
