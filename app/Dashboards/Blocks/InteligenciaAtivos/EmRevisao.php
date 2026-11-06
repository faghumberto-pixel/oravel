<?php

namespace App\Dashboards\Blocks\InteligenciaAtivos;

use App\Dashboards\Payload;

class EmRevisao extends IaBlock
{
    public static function key(): string
    {
        return 'ativos_ia.em_revisao';
    }

    public static function title(): string
    {
        return 'Pedem revisão';
    }

    public static function subtitle(): string
    {
        return 'Custo de manutenção já na faixa de atenção';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $linhas = $this->ranking();
        $revisar = $linhas->filter(fn ($l) => $l['situacao']['chave'] === 'revisar');
        $n = $revisar->count();

        return Payload::kpi(
            $n,
            $n === 1 ? 'equipamento' : 'equipamentos',
            $n > 0 ? $this->compacto((float) $revisar->sum('custo_periodo')).' em manutenção nos últimos 12 meses' : 'Nenhum na faixa de atenção',
            'n',
            ['pct' => $linhas->isNotEmpty() ? (int) round($n / $linhas->count() * 100) : 0, 'tone' => 'warn'],
        );
    }
}
