<?php

namespace App\Filament\Widgets\GestaoAVista;

use App\Filament\Widgets\Charts\GaugeChart;
use App\Services\GestaoAVistaService;
use App\Support\Tenancy;

/**
 * KPI de topo da Coluna 1 (% Manutenção Realizada) do dashboard "Gestão à
 * Vista". mount() sobrescrito acrescenta os 4 filtros globais (from/until/
 * branchId/assetId) aos parâmetros de GaugeChart::mount() -- todos com
 * default (LSP permite adicionar parâmetro novo desde que opcional, só
 * proíbe tornar um existente obrigatório). Quem instancia via
 * @livewire(ManutencaoRealizadaGauge::class, ['from' => ..., 'until' => ...])
 * passa só os filtros; value/target são calculados aqui dentro, não pelo
 * caller.
 */
class ManutencaoRealizadaGauge extends GaugeChart
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
            $resultado = $service->percentualManutencaoRealizada([
                'from' => $from,
                'until' => $until,
                'branchId' => $branchId,
                'assetId' => $assetId,
            ]);
            $value = $resultado['percentual'] ?? 0.0;
            $target = $tenant->getTarget('manutencao_realizada');
        }

        parent::mount(
            value: $value,
            target: $target,
            chartTitle: 'Manutenção Realizada',
        );
    }
}
