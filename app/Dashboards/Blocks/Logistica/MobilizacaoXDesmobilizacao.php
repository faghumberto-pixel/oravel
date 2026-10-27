<?php

namespace App\Dashboards\Blocks\Logistica;

use App\Dashboards\Payload;
use App\Models\EquipmentMovement;

class MobilizacaoXDesmobilizacao extends LogisticaBlock
{
    public static function key(): string
    {
        return 'logistica.mobilizacao_x_desmobilizacao';
    }

    public static function title(): string
    {
        return 'Mobilização x desmobilização';
    }

    public static function subtitle(): string
    {
        return 'Movimentações por mês, últimos 6 meses';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $inicio = now()->startOfMonth()->subMonths(5);
        $movs = EquipmentMovement::query()->where('created_at', '>=', $inicio)->get(['type', 'created_at']);

        $labels = [];
        $a = [];
        $b = [];
        for ($i = 0; $i < 6; $i++) {
            $mes = $inicio->copy()->addMonths($i);
            $doMes = $movs->filter(fn ($m) => $m->created_at->isSameMonth($mes));
            $labels[] = ucfirst($mes->translatedFormat('M'));
            $a[] = $doMes->where('type', EquipmentMovement::TYPE_MOBILIZACAO)->count();
            $b[] = $doMes->where('type', EquipmentMovement::TYPE_DESMOBILIZACAO)->count();
        }

        return Payload::cols($labels, $a, $b, 'Mobilização', 'Desmobilização');
    }
}
