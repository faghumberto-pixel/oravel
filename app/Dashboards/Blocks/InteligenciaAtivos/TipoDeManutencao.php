<?php

namespace App\Dashboards\Blocks\InteligenciaAtivos;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Carbon;

class TipoDeManutencao extends IaBlock
{
    public static function key(): string
    {
        return 'ativos_ia.tipo_manutencao';
    }

    public static function title(): string
    {
        return 'Para onde vai o dinheiro';
    }

    public static function subtitle(): string
    {
        return 'Custo por tipo de manutenção (12 meses): preventiva custa menos que corretiva';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $inicio = Carbon::now()->startOfMonth()->subMonths(11);

        $porTipo = MaintenanceOrder::query()
            ->whereRaw('coalesce(finished_at, created_at) >= ?', [$inicio])
            ->selectRaw('coalesce(maintenance_type, \'Corretiva\') as tipo, sum(total_order_cost) as total')
            ->groupBy('tipo')->pluck('total', 'tipo');

        $cores = ['Preventiva' => '#38bdf8', 'Corretiva' => '#f59e0b', 'Emergência' => '#ef4444'];
        $fatias = $porTipo->map(fn ($v, $tipo) => [$tipo, round((float) $v, 2), $cores[$tipo] ?? '#94a3b8'])->sortByDesc(fn ($f) => $f[1])->values()->all();

        return Payload::donut($fatias, $this->compacto((float) $porTipo->sum()), '12 meses');
    }
}
