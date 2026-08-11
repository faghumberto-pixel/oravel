<?php

namespace App\Filament\Widgets\GestaoAVista;

use App\Services\GestaoAVistaService;
use App\Support\Tenancy;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * 3 cards de resumo do dashboard "Gestao a Vista" (aba do Painel de
 * Controle): OS Planejadas/Concluidas/Atrasadas no periodo filtrado.
 * Mesmo padrao de canView()/getStats() de MaintenanceOrderStats, so' que
 * recebendo os 4 filtros globais da pagina via mount() (com defaults,
 * pra nao violar LSP em Widget::mount() -- ver
 * App\Filament\Widgets\Charts\GaugeChart::mount() pro mesmo comentario).
 */
class OsResumoStats extends BaseWidget
{
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

    public static function canView(): bool
    {
        return (bool) Tenancy::current();
    }

    protected function getStats(): array
    {
        $tenant = Tenancy::current();

        if (! $tenant) {
            return [
                Stat::make('OS Planejadas', 0)->color('gray'),
                Stat::make('OS Concluídas', 0)->color('gray'),
                Stat::make('OS Atrasadas', 0)->color('gray'),
            ];
        }

        $service = new GestaoAVistaService($tenant->id);
        $resumo = $service->resumoOs([
            'from' => $this->from,
            'until' => $this->until,
            'branchId' => $this->branchId,
            'assetId' => $this->assetId,
        ]);

        return [
            Stat::make('OS Planejadas', $resumo['planejadas'])
                ->description('Abertas no período')
                ->color('info'),

            Stat::make('OS Concluídas', $resumo['concluidas'])
                ->description('Finalizadas no período')
                ->color('success'),

            Stat::make('OS Atrasadas', $resumo['atrasadas'])
                ->description('SLA estourado, ainda abertas')
                ->color($resumo['atrasadas'] > 0 ? 'danger' : 'success'),
        ];
    }
}
