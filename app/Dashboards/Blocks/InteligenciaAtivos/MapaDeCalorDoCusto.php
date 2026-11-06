<?php

namespace App\Dashboards\Blocks\InteligenciaAtivos;

use App\Dashboards\Payload;
use App\Services\CustoManutencaoService;

class MapaDeCalorDoCusto extends IaBlock
{
    public static function key(): string
    {
        return 'ativos_ia.mapa_calor';
    }

    public static function title(): string
    {
        return 'Mapa de calor do custo por equipamento';
    }

    public static function subtitle(): string
    {
        return 'Os 10 que mais gastaram, mês a mês (últimos 12 meses)';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $linhas = $this->ranking()->sortByDesc('custo_periodo')->take(10)->filter(fn ($l) => $l['custo_periodo'] > 0)->values();

        return Payload::heat(
            array_map(fn ($r) => mb_substr($r, 0, 3), CustoManutencaoService::rotulosSerie()),
            $linhas->map(fn ($l) => [
                $this->nomeCurto($l), $l['categoria'], $l['serie'], $this->compacto($l['custo_periodo']),
                $l['situacao']['chave'] === 'ok' ? null : Payload::pill($this->tom($l['situacao']['cor']), $l['situacao']['chave'] === 'revisar' ? 'revisar' : 'trocar'),
            ])->all(),
        );
    }
}
