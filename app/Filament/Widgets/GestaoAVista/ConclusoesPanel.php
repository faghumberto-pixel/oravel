<?php

namespace App\Filament\Widgets\GestaoAVista;

use App\Services\GestaoAVistaService;
use App\Support\Tenancy;
use Filament\Widgets\Widget;

/**
 * Bloco de fechamento (rodapé, coluna direita): bullets dinâmicas geradas
 * por GestaoAVistaService::conclusoes(). Widget Blade simples, mesmo
 * padrão de CustoTotalMetricCard.
 */
class ConclusoesPanel extends Widget
{
    protected static string $view = 'filament.widgets.gestao-a-vista.conclusoes-panel';

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
     * @return array<int, string>
     */
    public function getBullets(): array
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return [];
        }

        $service = new GestaoAVistaService($tenant->id);

        return $service->conclusoes([
            'from' => $this->from,
            'until' => $this->until,
            'branchId' => $this->branchId,
            'assetId' => $this->assetId,
        ]);
    }
}
