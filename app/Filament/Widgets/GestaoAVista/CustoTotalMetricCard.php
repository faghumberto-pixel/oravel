<?php

namespace App\Filament\Widgets\GestaoAVista;

use App\Services\GestaoAVistaService;
use App\Support\Tenancy;
use Filament\Widgets\Widget;

/**
 * Card de custo total de manutenção do dashboard "Gestão à Vista" (valor
 * BRL + variação % vs. período anterior) -- widget Blade custom simples,
 * mesmo padrão de estrutura de CrmLeadMapWidget (extends Widget,
 * $view próprio), mas sem mapa/JS, só a view renderizando o array
 * já pronto do service.
 */
class CustoTotalMetricCard extends Widget
{
    protected static string $view = 'filament.widgets.gestao-a-vista.custo-total-metric-card';

    protected int|string|array $columnSpan = 'full';

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
     * @return array{valor_formatado: string, variacao_percentual: ?float}
     */
    public function getCusto(): array
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return ['valor_formatado' => 'R$ 0,00', 'variacao_percentual' => null];
        }

        $service = new GestaoAVistaService($tenant->id);
        $custo = $service->custoTotal([
            'from' => $this->from,
            'until' => $this->until,
            'branchId' => $this->branchId,
            'assetId' => $this->assetId,
        ]);

        return [
            'valor_formatado' => $custo['valor_formatado'],
            'variacao_percentual' => $custo['variacao_percentual'],
        ];
    }
}
