<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;
use App\Models\SlaTimer;

/** F4: % de OS cuja Resolução (relógio de SLA) foi concluída dentro do prazo no período. */
class SlaResolucaoCumprida extends ManutencaoBlock
{
    public static function key(): string
    {
        return 'manutencao.sla_resolucao_cumprida';
    }

    public static function title(): string
    {
        return 'SLA de resolução cumprido';
    }

    public static function subtitle(): string
    {
        return 'OS concluídas dentro do prazo de resolução';
    }

    public static function size(): int
    {
        return 3;
    }

    public static function defaultConfig(): array
    {
        return ['meta' => 90, 'dias' => 30];
    }

    public function data(array $config): array
    {
        $meta = (int) ($config['meta'] ?? 90);

        $resultados = SlaTimer::query()
            ->where('subject_type', MaintenanceOrder::class)->where('clock', SlaTimer::RESOLUTION)
            ->whereIn('result', ['met', 'breached'])->where('stopped_at', '>=', now()->subDays((int) ($config['dias'] ?? 30)))
            ->pluck('result');

        if ($resultados->isEmpty()) {
            return Payload::kpi('—', '', 'Sem resoluções com SLA no período');
        }

        $noPrazo = $resultados->filter(fn ($r) => $r === 'met')->count();
        $pct = (int) round($noPrazo / $resultados->count() * 100);

        return Payload::kpi($pct.'%', '', $noPrazo.' de '.$resultados->count().' no prazo (meta '.$meta.'%)', $pct >= $meta ? 'up' : 'dn', ['pct' => $pct, 'tone' => $pct >= $meta ? 'ok' : 'warn']);
    }
}
