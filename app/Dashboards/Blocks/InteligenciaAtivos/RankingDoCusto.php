<?php

namespace App\Dashboards\Blocks\InteligenciaAtivos;

use App\Dashboards\Payload;

class RankingDoCusto extends IaBlock
{
    public static function key(): string
    {
        return 'ativos_ia.ranking_valor';
    }

    public static function title(): string
    {
        return 'Quanto do valor já foi gasto em manutenção';
    }

    public static function subtitle(): string
    {
        return 'Custo acumulado ÷ valor de aquisição, com as linhas de revisar e de trocar ou vender';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $linhas = $this->ranking()->filter(fn ($l) => $l['razao'] !== null)->sortByDesc('razao')->take(8)->values();

        return Payload::ranking(
            $linhas->map(fn ($l) => [
                $this->nomeCurto($l),
                $this->compacto($l['custo_total']).' de '.$this->compacto($l['valor']),
                (int) round($l['razao'] * 100),
                round($l['razao'] * 100).'%',
                $this->tom($l['situacao']['cor']),
            ])->all(),
            [(int) round(config('custo_manutencao.limiar_revisar') * 100), (int) round(config('custo_manutencao.limiar_trocar_vender') * 100)],
            'Linhas verticais: '.round(config('custo_manutencao.limiar_revisar') * 100).'% (revisar) e '.round(config('custo_manutencao.limiar_trocar_vender') * 100).'% (trocar ou vender).',
        );
    }
}
