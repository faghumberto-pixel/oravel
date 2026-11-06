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
        return 6;
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

        $eventos = $movs->map(fn ($m) => [
            $m->scheduled_at?->format('d/m H\hi') ?? 'Sem data',
            ($m->asset?->name ?? 'Equipamento').' — '.$this->rotuloDoTipo($m->type),
            $m->notes ?: 'sem trajeto informado',
            match ($m->status) {
                EquipmentMovement::STATUS_EM_ANDAMENTO => 'warn',
                EquipmentMovement::STATUS_AGUARDANDO_APROVACAO => 'crit',
                default => 'a',
            },
            match ($m->status) {
                EquipmentMovement::STATUS_EM_ANDAMENTO => 'em andamento',
                EquipmentMovement::STATUS_AGUARDANDO_APROVACAO => 'aguarda aprovação',
                default => 'aguarda vistoria',
            },
        ])->all();

        return Payload::timeline($eventos, 'Nenhuma movimentação pendente.');
    }
}
