<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class AtendimentosPorSituacao extends ManutencaoBlock
{
    public static function key(): string
    {
        return 'manutencao.por_situacao';
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

        $linhas = [];
        foreach ($porStatus as $status => $total) {
            $linhas[] = [$status ?: 'Sem situação', $total, (string) $total];
        }
        $linhas[] = ['Aguardando peça', $aguardandoPeca, (string) $aguardandoPeca, 'warn'];
        $linhas[] = ['Concluídas hoje', $concluidasHoje, (string) $concluidasHoje, 'ok'];

        return Payload::bars($linhas);
    }
}
