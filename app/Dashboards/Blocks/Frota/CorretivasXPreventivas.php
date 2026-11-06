<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class CorretivasXPreventivas extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.corretivas_x_preventivas';
    }

    public static function title(): string
    {
        return 'Corretivas x preventivas';
    }

    public static function subtitle(): string
    {
        return 'Manutenções concluídas dos veículos por mês (emergências contam como corretivas)';
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
        $meses = 6;
        $inicio = now()->startOfMonth()->subMonths($meses - 1);
        $ordens = $this->osDosVeiculos()->whereIn('status', ['Concluída', 'Completado'])->where('finished_at', '>=', $inicio)->get(['finished_at', 'maintenance_type']);
        $rotulos = [];
        $corretivas = [];
        $preventivas = [];
        for ($i = 0; $i < $meses; $i++) {
            $mes = $inicio->copy()->addMonths($i);
            $doMes = $ordens->filter(fn ($o) => $o->finished_at->isSameMonth($mes));
            $rotulos[] = ucfirst($mes->translatedFormat('M'));
            $corretivas[] = $doMes->filter(fn ($o) => $o->maintenance_type !== MaintenanceOrder::TYPE_PREVENTIVE)->count();
            $preventivas[] = $doMes->filter(fn ($o) => $o->maintenance_type === MaintenanceOrder::TYPE_PREVENTIVE)->count();
        }

        return Payload::cols($rotulos, $corretivas, $preventivas, 'Corretivas', 'Preventivas');
    }
}
