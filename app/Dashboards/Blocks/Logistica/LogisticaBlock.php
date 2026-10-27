<?php

namespace App\Dashboards\Blocks\Logistica;

use App\Dashboards\Block;
use App\Models\EquipmentMovement;

/** Base dos blocos de Logística (mobilização, fretes, retiradas e saídas de veículos). */
abstract class LogisticaBlock extends Block
{
    public static function module(): string
    {
        return 'Logística';
    }

    public static function requiredFeature(): string
    {
        return 'tabela_equipment_movements';
    }

    public static function sourceModel(): string
    {
        return EquipmentMovement::class;
    }

    protected function brl(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }

    protected function rotuloDoTipo(?string $tipo): string
    {
        return match ($tipo) {
            EquipmentMovement::TYPE_MOBILIZACAO => 'Mobilização',
            EquipmentMovement::TYPE_DESMOBILIZACAO => 'Desmobilização',
            default => '—',
        };
    }
}
