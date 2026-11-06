<?php

namespace App\Dashboards\Blocks\InteligenciaAtivos;

use App\Dashboards\Payload;

class TrocarOuVender extends IaBlock
{
    public static function key(): string
    {
        return 'ativos_ia.trocar_vender';
    }

    public static function title(): string
    {
        return 'Considerar trocar ou vender';
    }

    public static function subtitle(): string
    {
        return 'Custo acumulado de manutenção acima do limite do valor do equipamento';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $linhas = $this->ranking();
        $criticos = $linhas->filter(fn ($l) => $l['situacao']['chave'] === 'trocar_vender');
        $n = $criticos->count();
        $acumulado = (float) $criticos->sum('custo_total');

        return Payload::kpi(
            $n,
            $n === 1 ? 'equipamento' : 'equipamentos',
            $n > 0 ? $this->compacto($acumulado).' já gastos em manutenção' : 'Nenhum acima do limite',
            $n > 0 ? 'dn' : 'up',
            ['pct' => $linhas->isNotEmpty() ? (int) round($n / $linhas->count() * 100) : 0, 'tone' => 'crit'],
        );
    }
}
