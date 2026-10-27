<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class TempoPrimeiroAtendimento extends ManutencaoBlock
{
    public static function key(): string
    {
        return 'manutencao.tempo_primeiro_atendimento';
    }

    public static function title(): string
    {
        return 'Tempo até o primeiro atendimento';
    }

    public static function subtitle(): string
    {
        return 'Horas, média semanal';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function defaultConfig(): array
    {
        return ['meta_horas' => 24, 'semanas' => 6];
    }

    public function data(array $config): array
    {
        $semanas = (int) ($config['semanas'] ?? 6);
        $inicio = now()->startOfWeek()->subWeeks($semanas - 1);

        $ordens = MaintenanceOrder::query()
            ->whereNotNull('started_at')
            ->where('created_at', '>=', $inicio)
            ->get(['created_at', 'started_at']);

        $labels = [];
        $valores = [];
        for ($i = 0; $i < $semanas; $i++) {
            $de = $inicio->copy()->addWeeks($i);
            $ate = $de->copy()->endOfWeek();
            $doPeriodo = $ordens->filter(fn ($os) => $os->created_at->between($de, $ate));
            $labels[] = 'S'.($i + 1);
            $valores[] = $doPeriodo->isEmpty() ? 0 : round($doPeriodo->avg(fn ($os) => $os->created_at->diffInMinutes($os->started_at) / 60), 1);
        }

        return Payload::line($labels, $valores, (int) ($config['meta_horas'] ?? 24));
    }
}
