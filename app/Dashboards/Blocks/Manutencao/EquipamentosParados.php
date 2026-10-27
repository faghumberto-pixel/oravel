<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Payload;
use App\Models\Asset;
use App\Models\AssetDowntimeEvent;

class EquipamentosParados extends ManutencaoBlock
{
    public static function key(): string
    {
        return 'manutencao.equipamentos_parados';
    }

    public static function title(): string
    {
        return 'Equipamentos parados';
    }

    public static function subtitle(): string
    {
        return 'Em manutenção agora';
    }

    public static function size(): int
    {
        return 2;
    }

    public static function defaultConfig(): array
    {
        return ['dias_alerta' => 5];
    }

    public function data(array $config): array
    {
        $parados = Asset::query()->where('status', Asset::STATUS_MANUTENCAO)->count();

        $dias = (int) ($config['dias_alerta'] ?? 5);
        $antigos = AssetDowntimeEvent::query()
            ->whereNull('ended_at')
            ->where('started_at', '<', now()->subDays($dias))
            ->distinct()
            ->count('asset_id');

        return Payload::kpi($parados, '', $antigos > 0 ? $antigos.' há mais de '.$dias.' dias' : 'Nenhum há mais de '.$dias.' dias', $antigos > 0 ? 'dn' : 'n');
    }
}
