<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;
use App\Models\SlaTimer;
use App\Services\Sla\SlaService;

/** F4: tempo médio de resolução (abertura até a conclusão, sem as pausas) das OS com SLA concluídas no período. */
class SlaTempoMedioResolucao extends ManutencaoBlock
{
    public static function key(): string
    {
        return 'manutencao.sla_tempo_medio_resolucao';
    }

    public static function title(): string
    {
        return 'Tempo médio de resolução';
    }

    public static function subtitle(): string
    {
        return 'Sem contar o tempo parado (aguardando peça)';
    }

    public static function size(): int
    {
        return 3;
    }

    public static function defaultConfig(): array
    {
        return ['dias' => 30];
    }

    public function data(array $config): array
    {
        $timers = SlaTimer::query()
            ->where('subject_type', MaintenanceOrder::class)->where('clock', SlaTimer::RESOLUTION)
            ->whereIn('result', ['met', 'breached'])->where('stopped_at', '>=', now()->subDays((int) ($config['dias'] ?? 30)))
            ->get();

        if ($timers->isEmpty()) {
            return Payload::kpi('—', '', 'Sem resoluções com SLA no período');
        }

        $media = (int) round($timers->avg(fn (SlaTimer $t) => $t->minutosGastos()));
        $meta = (int) round($timers->avg('target_minutes'));

        return Payload::kpi(SlaService::formatarMinutos($media), '', 'meta média '.SlaService::formatarMinutos($meta), $media <= $meta ? 'up' : 'dn');
    }
}
