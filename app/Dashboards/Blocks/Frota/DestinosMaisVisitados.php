<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\FrotaSaidaVeiculo;

class DestinosMaisVisitados extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.destinos';
    }

    public static function title(): string
    {
        return 'Destinos mais visitados';
    }

    public static function subtitle(): string
    {
        return 'Saídas dos últimos 60 dias por destino';
    }

    public static function size(): int
    {
        return 4;
    }

    public function data(array $config): array
    {
        $porDestino = FrotaSaidaVeiculo::query()->where('saida_em', '>=', now()->subDays(60))->selectRaw('destino, count(*) as total')->groupBy('destino')->orderByDesc('total')->limit(6)->pluck('total', 'destino');

        return Payload::ranking($this->linhasDeRanking($porDestino, fn ($n) => $n.($n === 1 ? ' saída' : ' saídas'), 'a'), [], 'A barra compara com o destino mais visitado.');
    }
}
