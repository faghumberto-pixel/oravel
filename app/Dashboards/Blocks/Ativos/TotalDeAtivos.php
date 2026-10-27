<?php

namespace App\Dashboards\Blocks\Ativos;

use App\Dashboards\Payload;
use App\Models\Asset;

class TotalDeAtivos extends AtivosBlock
{
    public static function key(): string
    {
        return 'ativos.total';
    }

    public static function title(): string
    {
        return 'Ativos cadastrados';
    }

    public static function subtitle(): string
    {
        return 'Equipamentos e veículos';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $valor = (float) Asset::query()->sum('acquisition_value');

        return Payload::kpi(Asset::query()->count(), '', $valor > 0 ? $this->brl($valor).' em valor de aquisição' : 'Sem valor de aquisição informado');
    }
}
