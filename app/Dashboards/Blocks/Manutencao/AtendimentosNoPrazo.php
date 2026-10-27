<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class AtendimentosNoPrazo extends ManutencaoBlock
{
    public static function key(): string
    {
        return 'manutencao.no_prazo';
    }

    public static function title(): string
    {
        return 'Atendidos no prazo';
    }

    public static function subtitle(): string
    {
        return 'OS com SLA concluídas nos últimos 30 dias';
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

        $ordens = MaintenanceOrder::query()
            ->whereIn('status', $this->concluidas())
            ->whereNotNull('sla_target_minutes')
            ->whereNotNull('finished_at')
            ->where('finished_at', '>=', now()->subDays((int) ($config['dias'] ?? 30)))
            ->get(['created_at', 'finished_at', 'sla_target_minutes']);

        if ($ordens->isEmpty()) {
            return Payload::kpi('—', '', 'Sem OS com SLA no período');
        }

        $noPrazo = $ordens->filter(fn ($os) => $os->created_at->diffInMinutes($os->finished_at) <= $os->sla_target_minutes)->count();
        $pct = (int) round($noPrazo / $ordens->count() * 100);

        return Payload::kpi(
            $pct.'%',
            '',
            $noPrazo.' de '.$ordens->count().' no prazo (meta '.$meta.'%)',
            $pct >= $meta ? 'up' : 'dn',
            ['pct' => $pct, 'tone' => $pct >= $meta ? 'ok' : 'warn'],
        );
    }
}
