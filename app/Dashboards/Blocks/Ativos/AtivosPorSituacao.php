<?php

namespace App\Dashboards\Blocks\Ativos;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\Asset;

class AtivosPorSituacao extends AtivosBlock
{
    use SeriesDeTempo;

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

        $cores = [Asset::STATUS_LOCADO => '#16a34a', Asset::STATUS_MANUTENCAO => '#f59e0b', Asset::STATUS_DISPONIVEL => '#38bdf8', Asset::STATUS_RESERVADO => '#a78bfa'];
        $valores = $porStatus->mapWithKeys(fn ($n, $status) => [$this->rotuloDaSituacao($status) => (int) $n]);
        $corPorRotulo = collect($cores)->mapWithKeys(fn ($c, $status) => [$this->rotuloDaSituacao($status) => $c])->all();

        return Payload::donut($this->fatias($valores, $corPorRotulo), (string) $porStatus->sum(), 'ativos');
    }
}
