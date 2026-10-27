<?php

namespace App\Dashboards\Blocks\Comercial;

use App\Dashboards\Payload;
use App\Models\CrmLead;
use App\Models\User;

class VendasPorVendedor extends ComercialBlock
{
    public static function key(): string
    {
        return 'comercial.vendas_por_vendedor';
    }

    public static function title(): string
    {
        return 'Vendas por vendedor';
    }

    public static function subtitle(): string
    {
        return 'Negócios fechados no mês, R$ mil';
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
        $porVendedor = CrmLead::query()
            ->where('stage', CrmLead::STAGE_CONVERTIDO)
            ->where('fechado_em', '>=', now()->startOfMonth())
            ->whereNotNull('vendedor_fechamento_id')
            ->selectRaw('vendedor_fechamento_id, sum(valor_fechado) as total')
            ->groupBy('vendedor_fechamento_id')
            ->orderByDesc('total')
            ->limit(6)
            ->pluck('total', 'vendedor_fechamento_id');

        $nomes = User::query()->whereIn('id', $porVendedor->keys())->pluck('name', 'id');

        return Payload::bars($porVendedor->map(fn ($v, $id) => [$nomes[$id] ?? 'Usuário removido', round($v / 1000, 1), 'R$ '.number_format($v / 1000, 1, ',', '.').' mil'])->values()->all());
    }
}
