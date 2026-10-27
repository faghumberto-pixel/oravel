<?php

namespace App\Dashboards\Blocks\Logistica;

use App\Dashboards\Payload;
use App\Models\EquipmentMovement;

class MovimentacoesDeHoje extends LogisticaBlock
{
    public static function key(): string
    {
        return 'logistica.movimentacoes_hoje';
    }

    public static function title(): string
    {
        return 'Movimentações hoje';
    }

    public static function subtitle(): string
    {
        return 'Mobilizações e desmobilizações agendadas';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $porTipo = EquipmentMovement::query()
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $detalhe = $porTipo->map(fn ($n, $tipo) => $n.' '.mb_strtolower($this->rotuloDoTipo($tipo)))->implode(', ');

        return Payload::kpi((int) $porTipo->sum(), '', $detalhe ?: 'Nenhuma movimentação agendada');
    }
}
