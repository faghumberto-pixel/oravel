<?php

namespace App\Dashboards\Blocks\Vendas;

use App\Dashboards\Payload;
use App\Models\CrmLead;
use App\Models\CrmMeta;
use App\Models\User;

class MetaPorVendedor extends VendasBlock
{
    public static function key(): string
    {
        return 'vendas.meta_vendedor';
    }

    public static function title(): string
    {
        return 'Meta por vendedor';
    }

    public static function subtitle(): string
    {
        return 'Quanto cada um já fechou da própria meta';
    }

    public static function size(): int
    {
        return 4;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_crm_metas';
    }

    public static function sourceModel(): string
    {
        return CrmMeta::class;
    }

    public function data(array $config): array
    {
        $mes = $this->mesAtual();
        $metas = CrmMeta::query()->whereDate('mes', $mes->toDateString())->whereNotNull('user_id')->where('meta_valor', '>', 0)->pluck('meta_valor', 'user_id');
        $feitos = CrmLead::query()->whereNotNull('fechado_em')->where('fechado_em', '>=', $mes)->whereNotNull('vendedor_fechamento_id')
            ->selectRaw('vendedor_fechamento_id, sum(valor_fechado) as total')->groupBy('vendedor_fechamento_id')->pluck('total', 'vendedor_fechamento_id');
        $nomes = User::query()->whereIn('id', $metas->keys())->pluck('name', 'id');

        $linhas = $metas->map(function ($meta, $userId) use ($feitos, $nomes) {
            $feito = (float) ($feitos[$userId] ?? 0);
            $pct = (int) round($feito / (float) $meta * 100);

            return [$nomes[$userId] ?? 'Usuário removido', $this->compacto($feito).' de '.$this->compacto((float) $meta), min(100, $pct), $pct.'%', $pct >= 100 ? 'ok' : ($pct >= 50 ? 'a' : 'n'), $pct];
        })->sortByDesc(fn ($l) => $l[5])->take(6)->map(fn ($l) => array_slice($l, 0, 5))->values()->all();

        return Payload::ranking($linhas, [], 'Meta do mês de cada vendedor; a barra mostra o quanto já foi fechado.');
    }
}
