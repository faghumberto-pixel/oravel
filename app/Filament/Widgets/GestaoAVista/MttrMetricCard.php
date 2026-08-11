<?php

namespace App\Filament\Widgets\GestaoAVista;

use App\Services\GestaoAVistaService;
use App\Support\Tenancy;
use Filament\Widgets\Widget;

/**
 * MTTR (h) -- mesmo padrão de MtbfMetricCard.
 */
class MttrMetricCard extends Widget
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
        $mttr = $service->mttr([
            'from' => $this->from,
            'until' => $this->until,
            'branchId' => $this->branchId,
            'assetId' => $this->assetId,
        ]);

        return [
            'titulo' => 'MTTR',
            'icone' => 'heroicon-o-wrench-screwdriver',
            'valor_formatado' => $mttr['valor_horas'] !== null
                ? number_format($mttr['valor_horas'], 2, ',', '.').' h'
                : 'Sem dados',
            'variacao_percentual' => $mttr['variacao_percentual'],
            // MTTR menor = repara mais rapido = melhor. Subir e' ruim.
            'variacao_e_boa_se_subir' => false,
        ];
    }

    private function vazio(): array
    {
        return [
            'titulo' => 'MTTR', 'icone' => 'heroicon-o-wrench-screwdriver',
            'valor_formatado' => 'Sem dados', 'variacao_percentual' => null,
            'variacao_e_boa_se_subir' => false,
        ];
    }
}
