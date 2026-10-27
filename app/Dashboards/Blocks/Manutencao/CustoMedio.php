<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class CustoMedio extends ManutencaoBlock
{
    public static function key(): string
    {
        return 'manutencao.custo_medio';
    }

    public static function title(): string
    {
        return 'Custo médio por atendimento';
    }

    public static function subtitle(): string
    {
        return 'Mão de obra, deslocamento e peças das OS concluídas';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $media = fn ($de, $ate) => MaintenanceOrder::query()
            ->whereIn('status', $this->concluidas())
            ->where('total_order_cost', '>', 0)
            ->whereBetween('finished_at', [$de, $ate])
            ->avg('total_order_cost');

        $atual = $media(now()->startOfMonth(), now());
        $anterior = $media(now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth());

        if ($atual === null) {
            return Payload::kpi('—', '', 'Sem OS concluída com custo neste mês');
        }

        if ($anterior === null) {
            return Payload::kpi($this->brl((float) $atual), '', 'Sem mês anterior para comparar');
        }

        $dif = (float) $atual - (float) $anterior;

        return Payload::kpi(
            $this->brl((float) $atual),
            '',
            ($dif <= 0 ? '-' : '+').$this->brl(abs($dif)).' sobre o mês anterior',
            $dif <= 0 ? 'up' : 'dn', // custo menor é melhor
        );
    }
}
