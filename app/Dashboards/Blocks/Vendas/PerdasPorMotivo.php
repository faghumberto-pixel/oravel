<?php

namespace App\Dashboards\Blocks\Vendas;

use App\Dashboards\Payload;
use App\Models\CrmLead;

class PerdasPorMotivo extends VendasBlock
{
    public static function key(): string
    {
        return 'vendas.perdas_motivo';
    }

    public static function title(): string
    {
        return 'Por que perdemos vendas';
    }

    public static function subtitle(): string
    {
        return 'Oportunidades perdidas nos últimos 90 dias';
    }

    public static function size(): int
    {
        return 6;
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
        $por = CrmLead::query()->where('stage', CrmLead::STAGE_PERDIDO)->where('fechado_em', '>=', now()->subDays(90))
            ->selectRaw("coalesce(nullif(lost_reason_category, ''), 'Sem motivo informado') as motivo, count(*) as total")
            ->groupBy('motivo')->orderByDesc('total')->limit(6)->pluck('total', 'motivo');

        $rows = $por->map(fn ($total, $motivo) => [(string) $motivo, (int) $total, $total.' '.($total === 1 ? 'venda' : 'vendas'), 'warn'])->values()->all();

        return Payload::bars($rows);
    }
}
