<?php

namespace App\Filament\Pages;

use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Chamados de Emergência (locadoras industrial/hospitalar) -- so' as O.S.
 * tipo TYPE_EMERGENCIA com sla_target_minutes preenchido, ordenadas pelas
 * mais criticas primeiro (mesma logica de cor de MaintenanceOrder::slaColor()).
 * Fecha em aberto: nao mostra concluida/cancelada, ja que o SLA de
 * "atendimento" para de contar quando o tecnico ja iniciou (started_at).
 */
class PainelSlaEmergencia extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationGroup = 'Relatórios';
    protected static ?string $navigationLabel = 'Painel de SLA';

    protected static ?string $title = 'Painel de SLA de Emergência';

    protected static string $view = 'filament.pages.painel-sla-emergencia';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class)
            && (Tenancy::current()?->hasModuleEnabled('sla_emergencia') ?? true);
    }

    public function getChamadosProperty(): Collection
    {
        $tenant = Tenancy::current();

        if (! $tenant) {
            return collect();
        }

        return MaintenanceOrder::where('tenant_id', $tenant->id)
            ->where('maintenance_type', MaintenanceOrder::TYPE_EMERGENCIA)
            ->whereNotNull('sla_target_minutes')
            ->whereNotIn('status', ['Concluída', 'Completado', 'Cancelada'])
            ->with(['asset', 'client', 'technician'])
            ->get()
            ->map(fn (MaintenanceOrder $order) => [
                'order' => $order,
                'minutos_decorridos' => $order->started_at ? null : $order->created_at->diffInMinutes(now()),
                'cor' => $order->slaColor(),
            ])
            ->sortBy(fn ($linha) => match ($linha['cor']) {
                'danger' => 0,
                'warning' => 1,
                'success' => 2,
                default => 3,
            })
            ->values();
    }

    public function getResumoProperty(): array
    {
        $chamados = $this->chamados->where('cor', '!=', 'gray');

        $total = $chamados->count();
        $noPrazo = $chamados->where('cor', 'success')->count();
        $vencidos = $chamados->where('cor', 'danger')->count();

        return [
            'total' => $total,
            'no_prazo_pct' => $total > 0 ? round($noPrazo / $total * 100) : 0,
            'vencidos_pct' => $total > 0 ? round($vencidos / $total * 100) : 0,
        ];
    }
}
