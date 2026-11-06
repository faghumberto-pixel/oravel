<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Carbon;

class CustoPorMes extends ManutencaoBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'manutencao.custo_por_mes';
    }

    public static function title(): string
    {
        return 'Custo de manutenção por mês';
    }

    public static function subtitle(): string
    {
        return 'OS concluídas, últimos 6 meses';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $meses = 6;
        $inicio = Carbon::now()->startOfMonth()->subMonths($meses - 1);
        $valores = $this->serieMensal(MaintenanceOrder::query()->whereIn('status', $this->concluidas()), 'finished_at', $meses, 'total_order_cost');
        $qtd = $this->serieMensal(MaintenanceOrder::query()->whereIn('status', $this->concluidas()), 'finished_at', $meses);

        $rotulos = collect(range(0, $meses - 1))->map(fn ($i) => ucfirst($inicio->copy()->addMonths($i)->translatedFormat('M')))->all();

        return Payload::cols($rotulos, array_map(fn ($v) => round($v / 1000, 1), $valores), $qtd, 'Custo (R$ mil)', 'OS concluídas');
    }
}
