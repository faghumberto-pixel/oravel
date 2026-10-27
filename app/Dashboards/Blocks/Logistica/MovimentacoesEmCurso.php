<?php

namespace App\Dashboards\Blocks\Logistica;

use App\Dashboards\Payload;
use App\Models\EquipmentMovement;

class MovimentacoesEmCurso extends LogisticaBlock
{
    public static function key(): string
    {
        return 'logistica.em_curso';
    }

    public static function title(): string
    {
        return 'Em andamento';
    }

    public static function subtitle(): string
    {
        return 'Ainda não concluídas';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $abertas = EquipmentMovement::query()->where('status', '!=', EquipmentMovement::STATUS_CONCLUIDO);
        $aprovacao = (clone $abertas)->where('status', EquipmentMovement::STATUS_AGUARDANDO_APROVACAO)->count();

        return Payload::kpi((clone $abertas)->count(), '', $aprovacao > 0 ? $aprovacao.' aguardando aprovação' : 'Nenhuma aguardando aprovação', $aprovacao > 0 ? 'dn' : 'n');
    }
}
