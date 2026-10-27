<?php

namespace App\Dashboards\Blocks\Ativos;

use App\Dashboards\Payload;
use App\Models\Asset;

class AtivosPorCliente extends AtivosBlock
{
    public static function key(): string
    {
        return 'ativos.por_cliente';
    }

    public static function title(): string
    {
        return 'Locados por cliente';
    }

    public static function subtitle(): string
    {
        return 'Clientes com mais equipamentos';
    }

    public static function size(): int
    {
        return 4;
    }

    public function data(array $config): array
    {
        $porCliente = Asset::query()
            ->with('client:id,name')
            ->where('status', Asset::STATUS_LOCADO)
            ->whereNotNull('client_id')
            ->get(['client_id'])
            ->groupBy(fn ($a) => $a->client?->name ?? 'Cliente removido')
            ->map->count()
            ->sortDesc()
            ->take(6);

        return Payload::bars($porCliente->map(fn ($n, $nome) => [$nome, $n, (string) $n])->values()->all());
    }
}
