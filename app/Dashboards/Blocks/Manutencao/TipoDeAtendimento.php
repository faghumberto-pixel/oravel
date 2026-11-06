<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class TipoDeAtendimento extends ManutencaoBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'manutencao.tipo_atendimento';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function title(): string
    {
        return 'Tipo de atendimento';
    }

    public static function subtitle(): string
    {
        return 'Últimos 30 dias';
    }

    public function data(array $config): array
    {
        $porTipo = MaintenanceOrder::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->whereNotIn('status', ['Cancelada'])
            ->selectRaw('maintenance_type, count(*) as total')
            ->groupBy('maintenance_type')
            ->orderByDesc('total')
            ->pluck('total', 'maintenance_type');

        $valores = $porTipo->mapWithKeys(fn ($n, $tipo) => [($tipo ?: 'Sem tipo') => (int) $n]);

        return Payload::donut($this->fatias($valores), (string) $porTipo->sum(), 'OS em 30 dias');
    }
}
