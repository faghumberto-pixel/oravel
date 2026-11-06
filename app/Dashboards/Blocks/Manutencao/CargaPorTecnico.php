<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;

class CargaPorTecnico extends ManutencaoBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'manutencao.carga_tecnico';
    }

    public static function title(): string
    {
        return 'Carga por técnico';
    }

    public static function subtitle(): string
    {
        return 'Atendimentos em aberto de cada técnico';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $porTecnico = $this->abertas()->with('technician:id,name')->whereNotNull('technician_id')->get(['technician_id'])
            ->groupBy(fn ($os) => $os->technician?->name ?? 'Técnico removido')->map->count()->sortDesc()->take(7);

        return Payload::ranking($this->linhasDeRanking($porTecnico, fn ($n) => $n.($n === 1 ? ' atendimento' : ' atendimentos'), 'a'), [], 'A barra compara com o técnico mais ocupado.');
    }
}
