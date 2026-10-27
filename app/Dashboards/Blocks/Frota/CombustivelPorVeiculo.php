<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\Asset;
use App\Models\FrotaAbastecimento;

class CombustivelPorVeiculo extends FrotaBlock
{
    public static function key(): string
    {
        return 'frota.combustivel_por_veiculo';
    }

    public static function title(): string
    {
        return 'Combustível por veículo';
    }

    public static function subtitle(): string
    {
        return 'Maiores gastos do mês, R$';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $porVeiculo = FrotaAbastecimento::query()
            ->whereBetween('abastecido_em', [now()->startOfMonth(), now()->endOfMonth()])
            ->selectRaw('ativo_id, sum(valor_total) as total')
            ->groupBy('ativo_id')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('total', 'ativo_id');

        $nomes = Asset::query()->whereIn('id', $porVeiculo->keys())->pluck('name', 'id');

        return Payload::bars($porVeiculo->map(fn ($v, $id) => [$nomes[$id] ?? 'Veículo removido', (float) $v, number_format((float) $v, 2, ',', '.')])->values()->all());
    }
}
