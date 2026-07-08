<?php

namespace App\Filament\Pages;

use App\Models\EquipmentDamage;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Relatorio analitico: avarias por tipo + reincidencia (mesmo ativo, mesmo
 * tipo de dano, dentro de uma janela de dias). Nao tenta apontar causa raiz
 * automaticamente -- so expoe pro gestor os dados de apoio pra decidir
 * (peca aplicada na OS de cada ocorrencia, tecnico responsavel pela OS).
 *
 * Avarias sem damage_type (registradas antes desse campo existir) entram
 * na contagem "Nao classificado" e ficam de fora da reincidencia por tipo,
 * ja que nao ha o que comparar.
 */
class AvariasReincidencia extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationLabel = 'Avarias & Reincidência';

    protected static ?string $title = 'Avarias e Reincidência';

    protected static string $view = 'filament.pages.avarias-reincidencia';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', EquipmentDamage::class);
    }

    public int $days = 90;

    public function getDaysOptions(): array
    {
        return [30 => '30 dias', 60 => '60 dias', 90 => '90 dias', 180 => '180 dias'];
    }

    public function getPorTipoProperty(): Collection
    {
        $labels = EquipmentDamage::damageTypeLabels();

        return EquipmentDamage::query()
            ->selectRaw('damage_type, count(*) as total')
            ->where('created_at', '>=', now()->subDays($this->days))
            ->groupBy('damage_type')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'label' => $labels[$row->damage_type] ?? 'Não classificado',
                'total' => $row->total,
            ]);
    }

    public function getReincidenciasProperty(): Collection
    {
        $grupos = EquipmentDamage::query()
            ->select('asset_id', 'damage_type')
            ->selectRaw('count(*) as total')
            ->whereNotNull('damage_type')
            ->where('created_at', '>=', now()->subDays($this->days))
            ->groupBy('asset_id', 'damage_type')
            ->having(DB::raw('count(*)'), '>=', 2)
            ->orderByDesc('total')
            ->get();

        $labels = EquipmentDamage::damageTypeLabels();

        return $grupos->map(function ($grupo) use ($labels) {
            $ocorrencias = EquipmentDamage::query()
                ->where('asset_id', $grupo->asset_id)
                ->where('damage_type', $grupo->damage_type)
                ->where('created_at', '>=', now()->subDays($this->days))
                ->with(['asset', 'reportedBy', 'maintenanceOrder.technician', 'maintenanceOrder.materials.material'])
                ->orderByDesc('created_at')
                ->get();

            return [
                'asset' => $ocorrencias->first()?->asset,
                'damage_type_label' => $labels[$grupo->damage_type] ?? 'Não classificado',
                'total' => $grupo->total,
                'ocorrencias' => $ocorrencias,
            ];
        });
    }
}
