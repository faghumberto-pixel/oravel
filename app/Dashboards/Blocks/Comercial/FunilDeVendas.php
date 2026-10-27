<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\CrmLead;

class FunilDeVendas extends ComercialBlock
{
    public static function key(): string
    {
        return 'comercial.funil';
    }

    public static function title(): string
    {
        return 'Funil de vendas';
    }

    public static function subtitle(): string
    {
        return 'Oportunidades por etapa';
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
        $porEtapa = CrmLead::query()
            ->selectRaw('stage, count(*) as total')
            ->groupBy('stage')
            ->pluck('total', 'stage');

        $etapas = [
            [CrmLead::STAGE_NOVO, 'Novos contatos'],
            [CrmLead::STAGE_CONTATO_INICIADO, 'Contato iniciado'],
            [CrmLead::STAGE_QUALIFICADO, 'Qualificados'],
            [CrmLead::STAGE_CONVERTIDO, 'Convertidos'],
        ];

        return Payload::funnel(array_map(fn ($e) => [$e[1], (int) ($porEtapa[$e[0]] ?? 0)], $etapas));
    }
}
