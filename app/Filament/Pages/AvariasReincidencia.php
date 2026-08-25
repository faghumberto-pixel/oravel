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

    /**
     * Reincidência por CLIENTE, não por Ativo -- pedido do usuário
     * 2026-08-25 (item 3 do roteiro de artefatos comerciais): a
     * reincidência por ativo não distingue se o padrão é do equipamento
     * (peça ruim, desgaste) ou de um cliente específico que devolve
     * equipamentos danificados com frequência. Só entram avarias
     * cobráveis (mau_uso/dano_cliente) -- desgaste natural não é "culpa"
     * de ninguém, não faz sentido contar aqui. Cliente vem de
     * MaintenanceOrder.client_id (mais direto que ir por Asset/Contract,
     * que não têm client_id garantido no momento da avaria).
     */
    public function getReincidenciasPorClienteProperty(): Collection
    {
        $causasCobraveis = [EquipmentDamage::CAUSE_MAU_USO, EquipmentDamage::CAUSE_DANO_CLIENTE];

        $grupos = EquipmentDamage::query()
            ->join('maintenance_orders', 'maintenance_orders.id', '=', 'equipment_damages.maintenance_order_id')
            ->whereNotNull('maintenance_orders.client_id')
            ->whereIn('equipment_damages.cause', $causasCobraveis)
            ->where('equipment_damages.created_at', '>=', now()->subDays($this->days))
            ->groupBy('maintenance_orders.client_id')
            ->selectRaw('maintenance_orders.client_id, count(*) as total')
            ->having(DB::raw('count(*)'), '>=', 2)
            ->orderByDesc('total')
            ->get();

        return $grupos->map(function ($grupo) use ($causasCobraveis) {
            $ocorrencias = EquipmentDamage::query()
                ->join('maintenance_orders', 'maintenance_orders.id', '=', 'equipment_damages.maintenance_order_id')
                ->where('maintenance_orders.client_id', $grupo->client_id)
                ->whereIn('equipment_damages.cause', $causasCobraveis)
                ->where('equipment_damages.created_at', '>=', now()->subDays($this->days))
                ->with(['asset', 'reportedBy', 'maintenanceOrder.client'])
                ->select('equipment_damages.*')
                ->orderByDesc('equipment_damages.created_at')
                ->get();

            return [
                'client' => $ocorrencias->first()?->maintenanceOrder?->client,
                'total' => $grupo->total,
                'ocorrencias' => $ocorrencias,
            ];
        });
    }
}
