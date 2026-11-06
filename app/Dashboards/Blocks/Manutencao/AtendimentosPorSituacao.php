<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class AtendimentosPorSituacao extends ManutencaoBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'manutencao.por_situacao';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function title(): string
    {
        return 'Atendimentos por situação';
    }

    public static function subtitle(): string
    {
        return 'OS em aberto e concluídas hoje';
    }

    public function data(array $config): array
    {
        $abertas = $this->abertas()
            ->selectRaw('status, internal_status, count(*) as total')
            ->groupBy('status', 'internal_status')
            ->get();

        $aguardandoPeca = (int) $abertas->where('internal_status', 'aguardando_peca')->sum('total');
        $porStatus = $abertas->where('internal_status', '!=', 'aguardando_peca')->groupBy('status')->map(fn ($g) => (int) $g->sum('total'));

        $concluidasHoje = MaintenanceOrder::query()
            ->whereIn('status', $this->concluidas())
            ->whereBetween('finished_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();

        $valores = [];
        foreach ($porStatus as $status => $total) {
            $valores[$status ?: 'Sem situação'] = $total;
        }
        $valores['Aguardando peça'] = $aguardandoPeca;
        $valores['Concluídas hoje'] = $concluidasHoje;

        return Payload::donut($this->fatias($valores, ['Aguardando peça' => '#f59e0b', 'Concluídas hoje' => '#16a34a']), (string) array_sum($valores), 'atendimentos');
    }
}
