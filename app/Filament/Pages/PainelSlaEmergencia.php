<?php

namespace App\Filament\Pages;

use App\Models\MaintenanceOrder;
use App\Models\SlaTimer;
use App\Services\Sla\SlaService;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

/**
 * Painel de SLA (F2): todas as O.S. com cronômetro aberto (Resposta, Atendimento, Resolução), das mais críticas para as menos,
 * e os indicadores dos últimos 30 dias (% cumprido por relógio). Mantém a classe/rota/chave de menu do antigo painel de Emergência.
 */
class PainelSlaEmergencia extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bolt';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $navigationParentItem = 'Painéis';

    protected static ?string $navigationLabel = 'Painel de SLA';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Painel de SLA';

    protected static string $view = 'filament.pages.painel-sla-emergencia';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    public function getChamadosProperty(): Collection
    {
        $tenant = Tenancy::current();

        if (! $tenant) {
            return collect();
        }

        $avisarEm = SlaService::settings((string) $tenant->id)->warn_pct;
        $ordem = ['vencido' => 0, 'em_risco' => 1, 'no_prazo' => 2, 'pausado' => 3];

        return MaintenanceOrder::where('tenant_id', $tenant->id)
            ->whereHas('slaTimers', fn ($q) => $q->whereNull('stopped_at'))
            ->with(['asset', 'client', 'technician', 'slaTimers'])
            ->get()
            ->map(function (MaintenanceOrder $order) use ($avisarEm, $ordem) {
                $relogios = $order->slaTimers->filter(fn (SlaTimer $t) => $t->aberto())->map(function (SlaTimer $t) use ($avisarEm) {
                    $estado = $t->estado($avisarEm);

                    return ['label' => $t->label(), 'estado' => $estado, 'rotulo' => SlaTimer::rotuloDoEstado($estado), 'cor' => SlaTimer::corDoEstado($estado),
                        'restante' => match ($estado) {
                            'pausado' => null,
                            'vencido' => 'há '.SlaService::formatarMinutos(abs($t->minutosRestantes())),
                            default => SlaService::formatarMinutos($t->minutosRestantes()),
                        }, 'meta' => SlaService::formatarMinutos($t->target_minutes)];
                })->values();

                $pior = $relogios->sortBy(fn ($r) => $ordem[$r['estado']] ?? 9)->first();

                return ['order' => $order, 'relogios' => $relogios, 'pior' => $pior, 'rank' => $ordem[$pior['estado']] ?? 9];
            })
            ->sortBy('rank')
            ->values();
    }

    public function getResumoProperty(): array
    {
        $tenant = Tenancy::current();

        return $tenant ? SlaService::resumo((string) $tenant->id) : ['relogios' => [], 'abertos' => []];
    }
}
