<?php

namespace App\Dashboards\Blocks\Vendas;

use App\Dashboards\Payload;
use App\Models\CrmLead;
use App\Models\CrmMeta;

class MetaDoMes extends VendasBlock
{
    public static function key(): string
    {
        return 'vendas.meta_mes';
    }

    public static function title(): string
    {
        return 'Meta do mês';
    }

    public static function subtitle(): string
    {
        return 'Fechado contra a meta da equipe';
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
        // Meta da empresa (sem vendedor) quando existe; senão, a soma das metas individuais (nunca as duas, para não contar em dobro).
        $doMes = CrmMeta::query()->whereDate('mes', $mes->toDateString());
        $empresa = (float) (clone $doMes)->whereNull('user_id')->sum('meta_valor');
        $meta = $empresa > 0 ? $empresa : (float) (clone $doMes)->whereNotNull('user_id')->sum('meta_valor');
        $feito = (float) CrmLead::query()->whereNotNull('fechado_em')->where('fechado_em', '>=', $mes)->sum('valor_fechado');

        if ($meta <= 0) {
            return Payload::kpi($this->compacto($feito), '', 'Sem meta cadastrada para este mês', 'n');
        }

        $pct = (int) round($feito / $meta * 100);
        $esperado = (int) round(now()->day / now()->daysInMonth * 100);

        return Payload::kpi($pct, '%', $this->compacto($feito).' de '.$this->compacto($meta).' · esperado até hoje: '.$esperado.'%', $pct >= $esperado ? 'up' : 'dn', ['pct' => min(100, $pct)]);
    }
}
