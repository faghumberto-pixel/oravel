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
        return 'Visitas por técnico';
    }

    public static function size(): int
    {
        return 8;
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

        $linhas = $ordens->map(fn (MaintenanceOrder $os) => [
            $os->technician?->name ?? '—',
            $os->client?->name ?? $os->asset?->client?->name ?? '—',
            $os->asset?->name ?? '—',
            $os->maintenance_type ?: '—',
            match (true) {
                in_array($os->status, $this->concluidas(), true) => Payload::pill('ok', 'Concluída'),
                $os->status === 'Em Andamento' => Payload::pill('warn', 'Em atendimento'),
                default => Payload::pill('n', $os->scheduled_at->format('H\hi')),
            },
        ])->all();

        return Payload::table(['Técnico', 'Cliente', 'Máquina', 'Tipo', 'Situação'], $linhas, 'Nenhuma visita agendada para hoje.');
    }
}
