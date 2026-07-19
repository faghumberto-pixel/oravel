<?php

namespace App\Filament\Central\Pages;

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
     * @return array<int, array{stage: string, label: string, count: int, widthPercent: float}>
     */
    public function getFunnelStages(): array
    {
        $stages = SalesLead::stageLabels();
        unset($stages[SalesLead::STAGE_PERDIDO]);
        $stageIds = array_keys($stages);

        $counts = SalesLead::whereIn('pipeline_stage', $stageIds)
            ->selectRaw('pipeline_stage, count(*) as total')
            ->groupBy('pipeline_stage')
            ->pluck('total', 'pipeline_stage');

        // Cumulativo (leads NESSE estagio + em qualquer estagio mais a
        // frente), nao o total bruto de cada estagio isolado -- e' o que
        // garante a largura so' diminuir estagio a estagio, nunca "inchar"
        // no meio (um estagio do meio pode ter mais lead parado do que o
        // anterior, o que quebraria a forma de piramide invertida se
        // contasse cada estagio isolado). Pedido explicito do usuario:
        // "tem que ser uma piramide invertida".
        $cumulative = [];
        $running = 0;
        foreach (array_reverse($stageIds) as $stageId) {
            $running += (int) ($counts[$stageId] ?? 0);
            $cumulative[$stageId] = $running;
        }

        $topCount = max($cumulative[$stageIds[0]] ?? 0, 1);

        // Piso de 22% de largura -- uma faixa com 1 lead (ou 0) ainda fica
        // legivel, nao desaparece visualmente. Como o cumulativo ja e'
        // sempre decrescente, aplicar o mesmo piso em faixas menores nunca
        // faz uma faixa mais funda ficar mais larga que a anterior.
        $minWidth = 22.0;

        $rows = [];
        foreach ($stageIds as $stageId) {
            $count = (int) ($counts[$stageId] ?? 0);
            $cumulativeCount = $cumulative[$stageId];
            $widthPercent = $cumulativeCount > 0
                ? max($minWidth, round(($cumulativeCount / $topCount) * 100, 1))
                : $minWidth;

            $rows[] = [
                'stage' => $stageId,
                'label' => $stages[$stageId],
                'count' => $count,
                'widthPercent' => $widthPercent,
            ];
        }

        // topWidth/bottomWidth pra cada faixa ser um trapezio continuo --
        // o fundo de uma faixa bate com o topo da proxima, sem degrau. A
        // ultima faixa converge pra um ponto de verdade (bico do funil,
        // como a referencia visual pedida), nao so' afunila um pouco.
        foreach ($rows as $i => &$row) {
            $row['topWidth'] = $row['widthPercent'];
            $row['bottomWidth'] = $rows[$i + 1]['widthPercent'] ?? 0.0;
        }
        unset($row);

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
