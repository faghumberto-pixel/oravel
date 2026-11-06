<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Concerns\SeriesDeTempo;
use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class UltimasOcorrencias extends ManutencaoBlock
{
    use SeriesDeTempo;

    public static function key(): string
    {
        return 'manutencao.ultimas_ocorrencias';
    }

    public static function title(): string
    {
        return 'Últimas ocorrências';
    }

    public static function subtitle(): string
    {
        return 'Linha do tempo das OS mais recentes, emergências em destaque';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $ordens = MaintenanceOrder::query()->with('asset:id,name')->whereNotIn('status', ['Cancelada'])->orderByDesc('created_at')->limit(8)->get();

        return Payload::timeline($ordens->map(function ($os) {
            $emergencia = $os->maintenance_type === MaintenanceOrder::TYPE_EMERGENCIA;
            $aberta = ! in_array($os->status, $this->encerradas(), true);

            return [
                $os->created_at->format('d/m'), ($os->asset?->name ?? 'Equipamento').' — '.($os->maintenance_type ?: 'OS'),
                'OS '.($os->os_number ?: substr($os->id, 0, 6)).' · '.$os->status,
                $emergencia ? 'crit' : ($aberta ? 'warn' : 'ok'), $emergencia ? 'emergência' : ($aberta ? 'em aberto' : null),
            ];
        })->all(), 'Nenhuma ordem de serviço ainda.');
    }
}
