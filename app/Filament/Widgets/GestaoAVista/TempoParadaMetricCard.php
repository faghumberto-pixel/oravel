<?php

namespace App\Filament\Widgets\GestaoAVista;

use App\Services\GestaoAVistaService;
use App\Support\Tenancy;
use Filament\Widgets\Widget;

/**
 * Tempo de Parada Não Planejada (h) -- mesmo padrão de MtbfMetricCard.
 */
class TempoParadaMetricCard extends Widget
{
    protected static string $view = 'filament.widgets.gestao-a-vista.metric-card';

    protected ?string $from = null;

    protected ?string $until = null;

    protected ?string $branchId = null;

    protected ?string $assetId = null;

    public function mount(
        ?string $from = null,
        ?string $until = null,
        ?string $branchId = null,
        ?string $assetId = null,
    ): void {
        $this->from = $from;
        $this->until = $until;
        $this->branchId = $branchId;
        $this->assetId = $assetId;
    }

    public function getMetrica(): array
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return $this->vazio();
        }

        $service = new GestaoAVistaService($tenant->id);
        $parada = $service->tempoParadaNaoPlanejada([
            'from' => $this->from,
            'until' => $this->until,
            'branchId' => $this->branchId,
            'assetId' => $this->assetId,
        ]);

        return [
            'titulo' => 'Tempo de Parada Não Planejada',
            'icone' => 'heroicon-o-exclamation-triangle',
            'valor_formatado' => number_format($parada['valor_horas'], 1, ',', '.').' h',
            'variacao_percentual' => $parada['variacao_percentual'],
            // Parada menor = melhor. Subir e' ruim.
            'variacao_e_boa_se_subir' => false,
        ];
    }

    private function vazio(): array
    {
        return [
            'titulo' => 'Tempo de Parada Não Planejada', 'icone' => 'heroicon-o-exclamation-triangle',
            'valor_formatado' => '0,0 h', 'variacao_percentual' => null,
            'variacao_e_boa_se_subir' => false,
        ];
    }
}
