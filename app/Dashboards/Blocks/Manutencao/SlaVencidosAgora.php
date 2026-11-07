<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;
use App\Models\SlaTimer;
use App\Services\Sla\SlaService;
use App\Support\Tenancy;

/** F4: quantas OS têm algum relógio de SLA aberto e já estourado, e quantas estão em risco. */
class SlaVencidosAgora extends ManutencaoBlock
{
    public static function key(): string
    {
        return 'manutencao.sla_vencidos_agora';
    }

    public static function title(): string
    {
        return 'SLA vencido agora';
    }

    public static function subtitle(): string
    {
        return 'OS com relógio aberto e estourado';
    }

    public static function size(): int
    {
        return 3;
    }

    public function data(array $config): array
    {
        $tenantId = (string) Tenancy::current()?->id;
        $avisarEm = SlaService::settings($tenantId)->warn_pct;

        $estados = SlaTimer::query()->where('subject_type', MaintenanceOrder::class)->whereNull('stopped_at')->get()
            ->groupBy('subject_id')->map(fn ($timers) => $timers->map(fn (SlaTimer $t) => $t->estado($avisarEm))->all());

        $vencidas = $estados->filter(fn ($e) => in_array('vencido', $e, true))->count();
        $emRisco = $estados->filter(fn ($e) => ! in_array('vencido', $e, true) && in_array('em_risco', $e, true))->count();

        return Payload::kpi($vencidas, '', $this->plural($emRisco, 'OS em risco', 'OS em risco'), $vencidas > 0 ? 'dn' : 'up', ['pct' => $vencidas > 0 ? 100 : 0, 'tone' => $vencidas > 0 ? 'crit' : 'ok']);
    }
}
