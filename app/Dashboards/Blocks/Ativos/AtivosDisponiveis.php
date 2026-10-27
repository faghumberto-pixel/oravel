<?php

namespace App\Dashboards\Blocks\Ativos;

use App\Dashboards\Payload;
use App\Models\Asset;

class AtivosDisponiveis extends AtivosBlock
{
    public static function key(): string
    {
        return 'ativos.disponiveis';
    }

    public static function title(): string
    {
        return 'Disponíveis';
    }

    public static function subtitle(): string
    {
        return 'Prontos para locar';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $reservados = Asset::query()->where('status', Asset::STATUS_RESERVADO)->count();

        return Payload::kpi(Asset::query()->where('status', Asset::STATUS_DISPONIVEL)->count(), '', $reservados > 0 ? $reservados.' reservados' : 'Nenhum reservado');
    }
}
