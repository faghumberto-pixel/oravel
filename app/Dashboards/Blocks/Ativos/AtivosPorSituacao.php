<?php

namespace App\Dashboards\Blocks\Ativos;

use App\Dashboards\Payload;
use App\Models\Asset;

class AtivosPorSituacao extends AtivosBlock
{
    public static function key(): string
    {
        return 'ativos.por_situacao';
    }

    public static function title(): string
    {
        return 'Ativos por situação';
    }

    public static function subtitle(): string
    {
        return 'Como a frota está agora';
    }

    public static function size(): int
    {
        return 4;
    }

    public function data(array $config): array
    {
        $porStatus = Asset::query()->selectRaw('status, count(*) as total')->groupBy('status')->orderByDesc('total')->pluck('total', 'status');

        return Payload::bars($porStatus->map(fn ($n, $status) => [
            $this->rotuloDaSituacao($status),
            (int) $n,
            (string) $n,
            match ($status) {
                Asset::STATUS_MANUTENCAO => 'warn',
                Asset::STATUS_LOCADO => 'ok',
                default => '',
            },
        ])->values()->all());
    }
}
