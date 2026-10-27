<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaSinistro;

class SinistrosEmAberto extends FrotaBlock
{
    public static function key(): string
    {
        return 'frota.sinistros_em_aberto';
    }

    public static function title(): string
    {
        return 'Sinistros em aberto';
    }

    public static function subtitle(): string
    {
        return 'Em orçamento ou reparo';
    }

    public static function size(): int
    {
        return 3;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_frota_sinistros';
    }

    public static function sourceModel(): string
    {
        return FrotaSinistro::class;
    }

    public function data(array $config): array
    {
        $abertos = FrotaSinistro::query()->whereNotIn('situacao', [FrotaSinistro::ENCERRADO, FrotaSinistro::CANCELADO]);
        $n = (clone $abertos)->count();
        $parados = (clone $abertos)->where('veiculo_parado', true)->count();

        return Payload::kpi($n, '', $parados > 0 ? $parados.' com veículo parado' : 'Nenhum veículo parado', $parados > 0 ? 'dn' : 'n');
    }
}
