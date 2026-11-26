<?php

namespace App\Dashboards\Blocks\Vendas;

use App\Dashboards\Payload;
use App\Models\CrmLead;

class LeadsParados extends VendasBlock
{
    public static function key(): string
    {
        return 'vendas.leads_parados';
    }

    public static function title(): string
    {
        return 'Oportunidades paradas';
    }

    public static function subtitle(): string
    {
        return 'Sem movimento há mais de 14 dias';
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
        $leads = CrmLead::query()->whereNotIn('stage', [CrmLead::STAGE_CONVERTIDO, CrmLead::STAGE_PERDIDO])->where('updated_at', '<', now()->subDays(14))
            ->orderByDesc('estimated_value')->limit(6)->get(['name', 'company_name', 'estimated_value', 'updated_at']);

        $itens = $leads->map(function ($l) {
            $dias = (int) $l->updated_at->diffInDays(now());

            return [$dias.' dias', $dias >= 30 ? 'crit' : 'warn', ($l->company_name ?: $l->name).' · '.$this->compacto((float) $l->estimated_value).' em jogo'];
        })->all();

        return Payload::alerts($itens, 'Nenhuma oportunidade parada. Bom ritmo!');
    }
}
