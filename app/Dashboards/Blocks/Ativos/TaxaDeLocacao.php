<?php

namespace App\Dashboards\Blocks\Ativos;

use App\Dashboards\Payload;
use App\Models\Asset;

class TaxaDeLocacao extends AtivosBlock
{
    public static function key(): string
    {
        return 'ativos.taxa_locacao';
    }

    public static function title(): string
    {
        return 'Taxa de locação';
    }

    public static function subtitle(): string
    {
        return 'Locados sobre o total de ativos';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $total = Asset::query()->count();
        if ($total === 0) {
            return Payload::kpi('—', '', 'Nenhum ativo cadastrado');
        }

        $locados = Asset::query()->where('status', Asset::STATUS_LOCADO)->count();
        $pct = (int) round($locados / $total * 100);

        return Payload::kpi($pct.'%', '', $locados.' de '.$total.' locados', 'n', ['pct' => $pct, 'tone' => 'ok']);
    }
}
