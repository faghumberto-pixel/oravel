<?php

namespace App\Filament\Widgets\GestaoAVista;

use App\Filament\Widgets\Charts\GaugeChart;
use App\Services\GestaoAVistaService;
use App\Support\Tenancy;

/**
 * KPI de topo da Coluna 3 (Efetividade da Manutenção), mesmo padrão de
 * ManutencaoRealizadaGauge.
 */
class EfetividadeGauge extends GaugeChart
{
    public function mount(
        float $value = 0,
        ?float $target = null,
        ?string $chartTitle = null,
        ?array $bands = null,
        ?string $needleColor = null,
        ?string $from = null,
        ?string $until = null,
        ?string $branchId = null,
        ?string $assetId = null,
    ): void {
        $tenant = Tenancy::current();

        if ($tenant) {
            $service = new GestaoAVistaService($tenant->id);
            $resultado = $service->efetividadeManutencao([
                'from' => $from,
                'until' => $until,
                'branchId' => $branchId,
                'assetId' => $assetId,
            ]);
            $value = $resultado['percentual'] ?? 0.0;
            $target = $tenant->getTarget('efetividade');
        }

        parent::mount(
            value: $value,
            target: $target,
            chartTitle: 'Efetividade da Manutenção',
        );
    }
}
