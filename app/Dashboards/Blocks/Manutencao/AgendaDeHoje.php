<?php

namespace App\Dashboards\Blocks\Manutencao;

use App\Dashboards\Payload;
use App\Models\MaintenanceOrder;

class AgendaDeHoje extends ManutencaoBlock
{
    public static function key(): string
    {
        return 'manutencao.agenda_hoje';
    }

    public static function title(): string
    {
        return 'Agenda de hoje';
    }

    public static function subtitle(): string
    {
        return 'Linha do tempo das visitas de hoje, por horário';
    }

    public static function size(): int
    {
        return 6;
    }

    public static function defaultConfig(): array
    {
        return ['limite' => 8];
    }

    public function data(array $config): array
    {
        $ordens = MaintenanceOrder::query()
            ->with(['technician:id,name', 'asset:id,name,client_id', 'asset.client:id,name', 'client:id,name'])
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()])
            ->whereNotIn('status', ['Cancelada'])
            ->orderBy('scheduled_at')
            ->limit((int) ($config['limite'] ?? 8))
            ->get();

        $eventos = $ordens->map(fn (MaintenanceOrder $os) => [
            $os->scheduled_at->format('H\hi'),
            ($os->asset?->name ?? 'Máquina').' — '.($os->client?->name ?? $os->asset?->client?->name ?? 'cliente'),
            ($os->technician?->name ?? 'sem técnico').' · '.($os->maintenance_type ?: 'atendimento'),
            match (true) {
                in_array($os->status, $this->concluidas(), true) => 'ok',
                $os->status === 'Em Andamento' => 'warn',
                default => 'a',
            },
            match (true) {
                in_array($os->status, $this->concluidas(), true) => 'concluída',
                $os->status === 'Em Andamento' => 'em atendimento',
                default => null,
            },
        ])->all();

        return Payload::timeline($eventos, 'Nenhuma visita agendada para hoje.');
    }
}
