<?php

namespace App\Dashboards\Blocks\Ativos;

use App\Dashboards\Payload;
use App\Models\Asset;

class AtivosEmManutencao extends AtivosBlock
{
    public static function key(): string
    {
        return 'ativos.em_manutencao';
    }

    public static function title(): string
    {
        return 'Em manutenção';
    }

    public static function subtitle(): string
    {
        return 'Parados na oficina';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $triagem = Asset::query()->where('status', Asset::STATUS_AGUARDANDO_TRIAGEM)->count();

        return Payload::kpi(
            Asset::query()->where('status', Asset::STATUS_MANUTENCAO)->count(),
            '',
            $triagem > 0 ? $triagem.' aguardando triagem' : 'Nenhum aguardando triagem',
            $triagem > 0 ? 'dn' : 'n',
        );
    }
}
