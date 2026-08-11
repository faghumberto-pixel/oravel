<?php

namespace App\Filament\Widgets\GestaoAVista;

use App\Filament\Widgets\Charts\BarLineComboChart;
use App\Services\GestaoAVistaService;
use App\Support\Tenancy;

/**
 * Gráfico de evolução mensal da Coluna 1: Planejado (barra, contagem de
 * OS) vs. % Realizado (linha, eixo secundário 0-100%) por mês. Antes era
 * duas áreas sobrepostas (Planejado/Realizado) -- ficava difícil comparar
 * quando os valores absolutos eram parecidos, já que uma área "escondia"
 * a outra visualmente. Combo de barra+linha em escalas separadas deixa a
 * meta (% atingido) e o volume (quantas OS) legíveis ao mesmo tempo.
 */
class ManutencaoRealizadaEvolucao extends BarLineComboChart
{
    public function mount(
        array $labels = [],
        array $barSeries = [],
        array $lineSeries = [],
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

            $planejado = $resultado['serie_mensal']['planejado'];
            $realizado = $resultado['serie_mensal']['realizado'];

            $labels = $resultado['serie_mensal']['labels'];
            $barSeries = ['name' => 'Planejado (OS)', 'color' => '#94a3b8', 'data' => $planejado];
            $lineSeries = [
                'name' => '% Realizado',
                'color' => '#199e70',
                'data' => array_map(
                    fn (int $p, int $r) => $p > 0 ? round(($r / $p) * 100, 1) : 0,
                    $planejado,
                    $realizado
                ),
            ];
        }

        parent::mount(
            labels: $labels,
            barSeries: $barSeries,
            lineSeries: $lineSeries,
            chartTitle: 'Manutenção Realizada por Mês',
        );
    }
}
