<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class IndicePreventiva extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.indice_preventiva';
    }

    public static function title(): string
    {
        return 'Quanto da manutenção é preventiva';
    }

    public static function subtitle(): string
    {
        return 'Últimos 90 dias: quanto mais preventiva, menos quebra';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function requiredFeature(): string
    {
        return 'tabela_maintenance_orders';
    }

    public static function sourceModel(): string
    {
        return MaintenanceOrder::class;
    }

    /** OS dos veiculos da frota (ativos marcados como veiculo). */
    protected function osDosVeiculos()
    {
        return MaintenanceOrder::query()->whereIn('asset_id', $this->veiculos()->pluck('id'));
    }

    public function data(array $config): array
    {
        $ordens = $this->osDosVeiculos()->whereIn('status', ['Concluída', 'Completado'])->where('finished_at', '>=', now()->subDays(90))->get(['maintenance_type', 'total_order_cost']);
        if ($ordens->isEmpty()) {
            return Payload::kpi('—', '', 'Nenhuma manutenção concluída nos últimos 90 dias');
        }
        $prev = $ordens->where('maintenance_type', MaintenanceOrder::TYPE_PREVENTIVE);
        $pct = (int) round($prev->count() / $ordens->count() * 100);
        $custoCorretiva = (float) $ordens->where('maintenance_type', '!=', MaintenanceOrder::TYPE_PREVENTIVE)->sum('total_order_cost');

        return Payload::kpi($pct.'%', '', $prev->count().' preventivas e '.($ordens->count() - $prev->count()).' corretivas · corretivas custaram R$ '.number_format($custoCorretiva, 0, ',', '.'), $pct >= 40 ? 'up' : 'dn', ['pct' => $pct, 'tone' => $pct >= 40 ? 'ok' : 'warn']);
    }
}
