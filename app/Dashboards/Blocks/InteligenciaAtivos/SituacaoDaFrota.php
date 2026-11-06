<?php

namespace App\Dashboards\Blocks\InteligenciaAtivos;

use App\Dashboards\Payload;
use App\Models\Asset;

class SituacaoDaFrota extends IaBlock
{
    private const ROTULOS = [
        'locado' => 'Locado', 'disponivel' => 'Disponível', 'manutencao' => 'Em manutenção', 'operando' => 'Em operação',
        'reservado' => 'Reservado', 'quarentena' => 'Quarentena', 'aguardando_triagem' => 'Aguardando triagem',
    ];

    public static function key(): string
    {
        return 'ativos_ia.situacao';
    }

    public static function title(): string
    {
        return 'Situação da frota';
    }

    public static function subtitle(): string
    {
        return 'Quantos equipamentos em cada situação';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $contagem = Asset::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $fatias = $contagem->map(fn ($n, $status) => [self::ROTULOS[$status] ?? ucfirst(str_replace('_', ' ', (string) $status)), (int) $n, self::STATUS_CORES[$status] ?? '#6b7280'])
            ->sortByDesc(fn ($f) => $f[1])->values()->all();

        return Payload::donut($fatias, (string) $contagem->sum(), 'equipamentos');
    }
}
