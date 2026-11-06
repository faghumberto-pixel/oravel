<?php

namespace App\Dashboards\Blocks\Frota;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class CustoDeManutencaoDaFrota extends FrotaCampoBlock
{
    public static function key(): string
    {
        return 'frota_log.custo_manutencao_periodo';
    }

    public static function title(): string
    {
        return 'Custo de manutenção da frota';
    }

    public static function subtitle(): string
    {
        return 'Por mês, últimos 6 meses (OS concluídas dos veículos), e quantas OS foram';
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
        $ordens = $this->osDosVeiculos()->whereIn('status', ['Concluída', 'Completado'])->where('finished_at', '>=', $inicio)->get(['finished_at', 'total_order_cost']);
        $rotulos = [];
        $custo = [];
        $qtd = [];
        for ($i = 0; $i < $meses; $i++) {
            $mes = $inicio->copy()->addMonths($i);
            $doMes = $ordens->filter(fn ($o) => $o->finished_at->isSameMonth($mes));
            $rotulos[] = ucfirst($mes->translatedFormat('M'));
            $custo[] = round($doMes->sum('total_order_cost') / 1000, 1);
            $qtd[] = $doMes->count();
        }

        return Payload::cols($rotulos, $custo, $qtd, 'Custo (R$ mil)', 'OS concluídas');
    }
}
