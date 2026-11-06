<?php

namespace App\Dashboards\Blocks\Logistica;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\FreightRecord;

class FretePorTipo extends LogisticaBlock
{
    use SeriesDeTempo;

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
        $valores = [];
        foreach ($rotulos as $tipo => $rotulo) {
            $valores[$rotulo.' ('.(int) ($porTipo->get($tipo)->viagens ?? 0).' viagens)'] = (float) ($porTipo->get($tipo)->total ?? 0);
        }

        return Payload::donut($this->fatias($valores), 'R$ '.number_format(array_sum($valores) / 1000, 1, ',', '.').' mil', 'no mês');
    }
}
