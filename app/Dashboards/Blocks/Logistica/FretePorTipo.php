<?php

namespace App\Dashboards\Blocks\Logistica;

use App\Dashboards\Payload;
use App\Models\FreightRecord;

class FretePorTipo extends LogisticaBlock
{
    public static function key(): string
    {
        return 'logistica.frete_por_tipo';
    }

    public static function title(): string
    {
        return 'Frete próprio x terceirizado';
    }

    public static function subtitle(): string
    {
        return 'Custo no mês, R$';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_freight_records';
    }

    public static function sourceModel(): string
    {
        return FreightRecord::class;
    }

    public function data(array $config): array
    {
        $porTipo = FreightRecord::query()
            ->whereBetween('data', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->selectRaw('tipo, sum(valor) as total, count(*) as viagens')
            ->groupBy('tipo')
            ->get()
            ->keyBy('tipo');

        $rotulos = [FreightRecord::TIPO_PROPRIO => 'Frota própria', FreightRecord::TIPO_TERCEIRIZADO => 'Transportadora'];
        $linhas = [];
        foreach ($rotulos as $tipo => $rotulo) {
            $linha = $porTipo->get($tipo);
            $linhas[] = [$rotulo.' ('.(int) ($linha->viagens ?? 0).' viagens)', (float) ($linha->total ?? 0), number_format((float) ($linha->total ?? 0), 2, ',', '.')];
        }

        return Payload::bars($linhas);
    }
}
