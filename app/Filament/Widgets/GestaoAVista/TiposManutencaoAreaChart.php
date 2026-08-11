<?php

namespace App\Filament\Widgets\GestaoAVista;

use App\Filament\Widgets\Charts\AreaChart;
use App\Services\GestaoAVistaService;
use App\Support\Tenancy;

/**
 * Evolução mensal de OS finalizadas por tipo (Preventiva/Corretiva
 * empilhadas, "Outras" quando existir volume relevante) -- substitui o
 * antigo TiposManutencaoDonutChart (doughnut de proporção estática):
 * área empilhada mostra a MESMA proporção, mas também como ela varia mês
 * a mês (ex.: mais corretiva que preventiva num mês específico é um sinal
 * de alerta que o donut de período único escondia).
 */
class TiposManutencaoAreaChart extends AreaChart
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
            $serie = $service->distribuicaoPorTipoMensal([
                'from' => $from,
                'until' => $until,
                'branchId' => $branchId,
                'assetId' => $assetId,
            ]);

            $labels = $serie['labels'];
            $seriesA = ['name' => 'Preventiva', 'color' => '#199e70', 'data' => $serie['preventiva']];
            $seriesB = ['name' => 'Corretiva', 'color' => '#e6534d', 'data' => $serie['corretiva']];
            // "Outras" (Avaria/Troca/Emergência etc concluídas) só entra na
            // pilha quando existe volume de verdade -- senão é uma 3a
            // legenda vazia sem utilidade nenhuma no gráfico.
            $seriesC = array_sum($serie['outras']) > 0
                ? ['name' => 'Outras', 'color' => '#94a3b8', 'data' => $serie['outras']]
                : [];
        }

        parent::mount(
            labels: $labels,
            seriesA: $seriesA,
            seriesB: $seriesB,
            chartTitle: 'Tipos de Manutenção',
            seriesC: $seriesC,
            empilhar: true,
        );
    }
}
