<?php

namespace App\Filament\Central\Pages;

use App\Filament\Central\Resources\SalesLeadResource;
use App\Models\SalesLead;
use Filament\Pages\Page;

/**
 * Funil de vendas de verdade -- visual afunilado (cada faixa mais
 * estreita que a anterior), nao um quadro (isso e' o Kanban, ver
 * App\Filament\Central\Pages\Kanban). Mostra volume de leads por
 * estagio e taxa de avanco entre eles, estilo classico de funil de
 * vendas B2B.
 */
class FunilVendas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $navigationLabel = 'Funil de Vendas';

    protected static ?string $navigationGroup = 'Comercial';

    protected static ?string $title = 'Funil de Vendas — CRM Comercial';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.central.pages.funil-vendas';

    /**
     * @return array<int, array{stage: string, label: string, count: int, topWidth: float, bottomWidth: float}>
     */
    public function getFunnelStages(): array
    {
        $stages = SalesLead::stageLabels();
        unset($stages[SalesLead::STAGE_PERDIDO]);
        $stageIds = array_keys($stages);
        $totalStages = count($stageIds);

        $counts = SalesLead::whereIn('pipeline_stage', $stageIds)
            ->selectRaw('pipeline_stage, count(*) as total')
            ->groupBy('pipeline_stage')
            ->pluck('total', 'pipeline_stage');

        // Largura FIXA por posicao (nao proporcional a contagem real) --
        // pedido explicito do usuario ("largos como uma piramide invertida,
        // a primeira e' a base"). Contagem proporcional foi tentada antes e
        // ficou ruim de proposito: numa esteira real com atrito forte entre
        // estagios (poucos leads sobrevivem ate' o fim), a largura
        // cumulativa colapsava quase tudo perto do piso minimo, so' o
        // primeiro estagio ficava largo. Aqui a piramide e' geometrica --
        // primeiro estagio = base (100%), ultimo = ponta (0%), divisao
        // igual entre os estagios -- sempre bem proporcionada, contagem
        // real so' aparece como numero em cada faixa.
        $rows = [];
        foreach ($stageIds as $i => $stageId) {
            $rows[] = [
                'stage' => $stageId,
                'label' => $stages[$stageId],
                'count' => (int) ($counts[$stageId] ?? 0),
                'topWidth' => round(100 * ($totalStages - $i) / $totalStages, 1),
                'bottomWidth' => round(100 * ($totalStages - $i - 1) / $totalStages, 1),
                // Clicar na faixa abre a lista de leads ja filtrada por esse
                // estagio -- pedido explicito do usuario.
                'url' => SalesLeadResource::getUrl('index', [
                    'tableFilters' => ['pipeline_stage' => ['value' => $stageId]],
                ]),
            ];
        }

        return $rows;
    }

    public function getLostCount(): int
    {
        return SalesLead::where('pipeline_stage', SalesLead::STAGE_PERDIDO)->count();
    }

    public function getConversionRate(array $rows): ?float
    {
        $first = $rows[0]['count'] ?? 0;
        $last = end($rows)['count'] ?? 0;

        if ($first === 0) {
            return null;
        }

        return round(($last / $first) * 100, 1);
    }
}
