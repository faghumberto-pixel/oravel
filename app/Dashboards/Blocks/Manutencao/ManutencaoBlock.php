<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Block;
use App\Dashboards\Concerns\OrdensDeServico;
use App\Models\MaintenanceOrder;

abstract class ManutencaoBlock extends Block
{
    use OrdensDeServico;

    public static function module(): string
    {
        return 'Manutenção';
    }

    public static function requiredFeature(): string
    {
        return 'tabela_maintenance_orders';
    }

    public static function sourceModel(): string
    {
        return MaintenanceOrder::class;
    }
}
