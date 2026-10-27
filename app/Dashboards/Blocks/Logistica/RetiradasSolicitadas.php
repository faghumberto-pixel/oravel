<?php

namespace App\Dashboards\Blocks\Logistica;

use App\Dashboards\Payload;
use App\Models\EquipmentPickupRequest;

class RetiradasSolicitadas extends LogisticaBlock
{
    public static function key(): string
    {
        return 'logistica.retiradas_solicitadas';
    }

    public static function title(): string
    {
        return 'Retiradas solicitadas';
    }

    public static function subtitle(): string
    {
        return 'Pedidos dos clientes ainda sem agenda';
    }

    public static function size(): int
    {
        return 3;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_equipment_pickup_requests';
    }

    public static function sourceModel(): string
    {
        return EquipmentPickupRequest::class;
    }

    public function data(array $config): array
    {
        $pendentes = EquipmentPickupRequest::query()->where('status', EquipmentPickupRequest::STATUS_SOLICITADO);
        $n = (clone $pendentes)->count();
        $antigas = (clone $pendentes)->where('requested_at', '<', now()->subDays(3))->count();

        return Payload::kpi($n, '', $antigas > 0 ? $antigas.' há mais de 3 dias' : 'Nenhuma há mais de 3 dias', $antigas > 0 ? 'dn' : 'n');
    }
}
