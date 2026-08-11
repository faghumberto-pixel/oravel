<?php

namespace App\Filament\Widgets\GestaoAVista;

use App\Services\GestaoAVistaService;
use App\Support\Tenancy;
use Filament\Widgets\Widget;

/**
 * MTBF (h) -- mesmo padrão de widget Blade simples de CustoTotalMetricCard.
 */
class MtbfMetricCard extends Widget
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

    /**
     * @return array{titulo: string, icone: string, valor_formatado: string, variacao_percentual: ?float, variacao_e_boa_se_subir: bool}
     */
    public function getMetrica(): array
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return $this->vazio();
        }

        $service = new GestaoAVistaService($tenant->id);
        $mtbf = $service->mtbf([
            'from' => $this->from,
            'until' => $this->until,
            'branchId' => $this->branchId,
            'assetId' => $this->assetId,
        ]);

        return [
            'titulo' => 'MTBF',
            'icone' => 'heroicon-o-arrow-trending-up',
            'valor_formatado' => $mtbf['valor_horas'] !== null
                ? number_format($mtbf['valor_horas'], 1, ',', '.').' h'
                : 'Sem dados',
            'variacao_percentual' => $mtbf['variacao_percentual'],
            // MTBF maior = mais tempo entre falhas = melhor. Subir e' bom.
            'variacao_e_boa_se_subir' => true,
        ];
    }

    private function vazio(): array
    {
        return [
            'titulo' => 'MTBF', 'icone' => 'heroicon-o-arrow-trending-up',
            'valor_formatado' => 'Sem dados', 'variacao_percentual' => null,
            'variacao_e_boa_se_subir' => true,
        ];
    }
}
