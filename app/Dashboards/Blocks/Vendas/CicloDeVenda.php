<?php

namespace App\Dashboards\Blocks\Vendas;

use App\Dashboards\Payload;
use App\Models\CrmLead;

class CicloDeVenda extends VendasBlock
{
    public static function key(): string
    {
        return 'vendas.ciclo_venda';
    }

    public static function title(): string
    {
        return 'Ciclo de venda';
    }

    public static function subtitle(): string
    {
        return 'Do primeiro contato ao fechamento';
    }

    public static function size(): int
    {
        return 4;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_crm_leads';
    }

    public static function sourceModel(): string
    {
        return CrmLead::class;
    }

    public function data(array $config): array
    {
        $ganhos = CrmLead::query()->where('stage', CrmLead::STAGE_CONVERTIDO)->where('fechado_em', '>=', now()->subDays(90))->get(['created_at', 'fechado_em', 'valor_fechado']);
        if ($ganhos->isEmpty()) {
            return Payload::kpi('—', '', 'Nenhuma venda fechada nos últimos 90 dias', 'n');
        }

        $dias = (int) round($ganhos->avg(fn ($l) => $l->created_at->diffInDays($l->fechado_em)));
        $ticket = (float) $ganhos->avg('valor_fechado');

        return Payload::kpi($dias, 'dias', $ganhos->count().' vendas · ticket médio '.$this->compacto($ticket), 'n');
    }
}
