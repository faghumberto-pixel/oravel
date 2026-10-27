<?php

namespace App\Dashboards\Blocks\Logistica;

use App\Dashboards\Payload;
use App\Models\EquipmentMovement;

class ProgramacaoDaLogistica extends LogisticaBlock
{
    public static function key(): string
    {
        return 'logistica.programacao';
    }

    public static function title(): string
    {
        return 'Programação';
    }

    public static function subtitle(): string
    {
        return 'Próximas movimentações não concluídas';
    }

    public static function size(): int
    {
        return 12;
    }

    public static function defaultConfig(): array
    {
        return ['limite' => 8];
    }

    public function data(array $config): array
    {
        $movs = EquipmentMovement::query()
            ->with('asset:id,name')
            ->where('status', '!=', EquipmentMovement::STATUS_CONCLUIDO)
            ->orderByRaw('scheduled_at is null, scheduled_at')
            ->limit((int) ($config['limite'] ?? 8))
            ->get();

        $linhas = $movs->map(fn ($m) => [
            $m->asset?->name ?? '—',
            $this->rotuloDoTipo($m->type),
            $m->notes ?: '—',
            match ($m->status) {
                EquipmentMovement::STATUS_EM_ANDAMENTO => Payload::pill('warn', 'Em andamento'),
                EquipmentMovement::STATUS_AGUARDANDO_APROVACAO => Payload::pill('crit', 'Aguardando aprovação'),
                default => Payload::pill('n', 'Aguardando vistoria'),
            },
            $m->scheduled_at?->format('d/m H\hi') ?? 'Sem data',
        ])->all();

        return Payload::table(['Equipamento', 'Tipo', 'Trajeto', 'Situação', '>Agendada'], $linhas, 'Nenhuma movimentação pendente.');
    }
}
