<?php

namespace App\Dashboards\Blocks\InteligenciaAtivos;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Carbon;

class OcorrenciasDaFrota extends IaBlock
{
    public static function key(): string
    {
        return 'ativos_ia.ocorrencias';
    }

    public static function title(): string
    {
        return 'Últimas ocorrências na frota';
    }

    public static function subtitle(): string
    {
        return 'Linha do tempo das ordens de serviço mais recentes, com o custo de cada uma';
    }

    public static function size(): int
    {
        return 6;
    }

    public function data(array $config): array
    {
        $ordens = MaintenanceOrder::query()->with('asset:id,name,patrimonio')->where('created_at', '>=', Carbon::now()->subDays(30))->orderByDesc('created_at')->limit(8)->get();

        $eventos = $ordens->map(function ($os) {
            $aberta = ! in_array($os->status, ['Concluída', 'Completado', 'Cancelada'], true);
            $emergencia = $os->maintenance_type === MaintenanceOrder::TYPE_EMERGENCIA;
            $nome = trim(($os->asset?->patrimonio ? $os->asset->patrimonio.' · ' : '').($os->asset?->name ?? 'Equipamento'));
            $custo = (float) $os->total_order_cost;

            return [
                $os->created_at->format('d/m'), $nome,
                'OS '.$os->os_number.' · '.$os->status.($custo > 0 ? ' · '.$this->compacto($custo) : ''),
                $emergencia ? 'crit' : ($aberta ? 'warn' : 'ok'), $emergencia ? 'emergência' : ($aberta ? 'em aberto' : null),
            ];
        })->all();

        return Payload::timeline($eventos, 'Nenhuma ordem de serviço nos últimos 30 dias.');
    }
}
