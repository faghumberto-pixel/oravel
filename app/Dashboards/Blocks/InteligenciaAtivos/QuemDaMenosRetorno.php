<?php

namespace App\Dashboards\Blocks\InteligenciaAtivos;

use App\Dashboards\Payload;
use App\Models\Contract;

class QuemDaMenosRetorno extends IaBlock
{
    public static function key(): string
    {
        return 'ativos_ia.menos_retorno';
    }

    public static function title(): string
    {
        return 'Quem dá menos retorno';
    }

    public static function subtitle(): string
    {
        return 'Receita de locação menos manutenção, no histórico do equipamento';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $receitas = Contract::query()->selectRaw('asset_id, sum(price) as receita')->whereNotNull('asset_id')->groupBy('asset_id')->pluck('receita', 'asset_id');

        $linhas = $this->ranking()
            ->map(function ($l) use ($receitas) {
                $receita = (float) ($receitas[$l['id']] ?? 0);

                return $l + ['receita' => $receita, 'saldo' => $receita - $l['custo_total']];
            })
            ->filter(fn ($l) => $l['receita'] > 0)
            ->sortBy('saldo')->take(6)->values();

        return Payload::table(
            ['Equipamento', '>Receita', '>Manutenção', '>Saldo'],
            $linhas->map(fn ($l) => [
                $this->nomeCurto($l),
                $this->compacto($l['receita']),
                $this->compacto($l['custo_total']),
                Payload::pill($l['saldo'] < 0 ? 'crit' : ($l['saldo'] < $l['receita'] * 0.2 ? 'warn' : 'ok'), $this->compacto($l['saldo'])),
            ])->all(),
            'Nenhum equipamento com locação no histórico.',
        );
    }
}
