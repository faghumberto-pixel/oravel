<?php

namespace App\Filament\Pages;

use App\Filament\Attributes\BelongsToFeature;
use App\Models\CriticalityLevel;
use App\Models\MaintenanceOrder;
use App\Models\MaintenanceStatusHistory;
use App\Models\SolicitacaoLocacao;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

#[BelongsToFeature('maintenance')]
class MaintenanceKanban extends Page
{
    protected static ?string $title = 'Oficina - Kanban Pátio';

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationGroup = 'PCM';

    protected static ?string $navigationLabel = 'Kanban do Pátio';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.maintenance-kanban';

    public static function getNavigationBadge(): ?string
    {
        return 'G P';
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'gray';
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    public $viewMode = 'oficina';

    public $search = '';

    public $technicianId = '';

    public array $hiddenStatuses = [];

    public bool $showFilters = false;

    /**
     * Define so' o valor INICIAL de $viewMode a partir da preferencia salva
     * do tenant (ui_customizations['kanban_default_view']) -- o toggle
     * manual do usuario (wire:click="$set('viewMode', ...)") continua
     * funcionando igual, so' muda o default de cada carregamento.
     */
    public function mount(): void
    {
        $this->viewMode = (Tenancy::current()?->ui_customizations ?? [])['kanban_default_view'] ?? 'oficina';
    }

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function getTotalOrdersCount(): int
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return 0;
        }

        $query = MaintenanceOrder::where('tenant_id', $tenant->id);

        if ($this->viewMode === 'oficina') {
            $query->where('status', '!=', 'Cancelada');
        }

        return $query->count();
    }

    public function getAverageLeadTime(): float
    {
        $records = $this->getRecords()->flatten();
        if ($records->isEmpty()) {
            return 0;
        }

        $totalDays = $records->sum(fn ($order) => $this->getDaysInStage($order));

        return round($totalDays / $records->count(), 1);
    }

    /**
     * Mapa de status -> título/cor, reaproveitado pelo Kanban e pela impressão.
     */
    public static function statusMap(string $viewMode): array
    {
        if ($viewMode === 'comercial') {
            return [
                'disponivel' => ['title' => 'Disponível no Pátio', 'color' => 'bg-emerald-600'],
                'locado' => ['title' => 'Em Obra (Locado)', 'color' => 'bg-blue-600'],
                'oficina' => ['title' => 'Retido / Oficina', 'color' => 'bg-red-600'],
            ];
        }

        $map = [
            'aguardando_diagnostico' => ['title' => 'Aguardando Diagnóstico', 'color' => 'bg-slate-600'],
            'em_manutencao' => ['title' => 'Em Manutenção', 'color' => 'bg-blue-600'],
            'aguardando_peca' => ['title' => 'Aguardando Peça', 'color' => 'bg-amber-500', 'flag' => 'Gargalo'],
            // Locadoras de construcao civil (giro de patio/oficina) --
            // 100% drag-and-drop manual, confirmado seguro:
            // MaintenanceOrderObserver::updated() so' automatiza
            // em_manutencao/teste_qualidade, nunca mexe nessas 2. Ficam
            // condicionadas a enabled_modules['kanban_oficina_extra'].
            'aguardando_peca_canibalizado' => ['title' => 'Aguardando Peças / Canibalizado', 'color' => 'bg-amber-700', 'flag' => 'Gargalo'],
            'teste_qualidade' => ['title' => 'Teste de Qualidade', 'color' => 'bg-purple-600'],
            'pronto_giro' => ['title' => 'Pronto para Giro/Pátio', 'color' => 'bg-teal-600'],
            'pendencia' => ['title' => 'Pendência', 'color' => 'bg-orange-500'],
            'concluido' => ['title' => 'Concluído', 'color' => 'bg-emerald-600'],
        ];

        if (! (Tenancy::current()?->hasModuleEnabled('kanban_oficina_extra') ?? true)) {
            unset($map['aguardando_peca_canibalizado'], $map['pronto_giro']);
        }

        return $map;
    }

    public function getStatuses(): array
    {
        return static::statusMap($this->viewMode);
    }

    /**
     * Colunas visíveis, respeitando o filtro de status ocultos.
     */
    public function getVisibleStatuses(): array
    {
        return array_diff_key($this->getStatuses(), array_flip($this->hiddenStatuses));
    }

    /**
     * Técnicos que aparecem em pelo menos uma OS do tenant -- base do filtro por usuário.
     */
    public function getTechniciansList(): Collection
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return collect();
        }

        $technicianIds = MaintenanceOrder::where('tenant_id', $tenant->id)
            ->whereNotNull('technician_id')
            ->distinct()
            ->pluck('technician_id');

        return User::whereIn('id', $technicianIds)->orderBy('name')->pluck('name', 'id');
    }

    /**
     * Query base compartilhada entre o board (Livewire) e a impressão (controller).
     */
    public static function buildQuery(string $viewMode, ?string $technicianId = null, ?string $search = null): Builder
    {
        $tenant = Tenancy::current();

        $query = MaintenanceOrder::where('tenant_id', $tenant?->id)
            ->with(['asset.abcMatrix', 'technician', 'client', 'parts', 'statusHistories'])
            ->withCount('evidences');

        if ($viewMode === 'oficina') {
            $query->where('status', '!=', 'Cancelada');
        }

        if (! empty($technicianId)) {
            $query->where('technician_id', $technicianId);
        }

        if (! empty(trim((string) $search))) {
            $term = '%'.trim($search).'%';
            $query->where(function ($subQuery) use ($term) {
                $subQuery->where('os_number', 'ilike', $term)
                    ->orWhere('description', 'ilike', $term)
                    ->orWhereHas('asset', fn ($q) => $q->where('name', 'ilike', $term));
            });
        }

        return $query;
    }

    public static function groupByStatus(Collection $orders, string $viewMode): Collection
    {
        $validStatuses = array_keys(static::statusMap($viewMode));

        return $orders->groupBy(function ($order) use ($validStatuses, $viewMode) {
            $status = ($viewMode === 'oficina') ? $order->internal_status : $order->commercial_status;

            return in_array($status, $validStatuses, true) ? $status : ($viewMode === 'oficina' ? 'aguardando_diagnostico' : 'oficina');
        });
    }

    public function getRecords(): Collection
    {
        if (! Tenancy::current()) {
            return collect();
        }

        $orders = static::buildQuery($this->viewMode, $this->technicianId, $this->search)->get();

        // Codigos de nivel marcados como urgente (CriticalityLevel.is_urgent,
        // configuravel pelo admin do tenant) -- mesma fonte unica de
        // criticidade usada pelo Painel de Criticidade (ver docblock de
        // PainelCriticidade), carregado uma vez, nao por O.S.
        $codigosUrgentes = CriticalityLevel::where('tenant_id', Tenancy::current()?->id)
            ->where('is_urgent', true)
            ->pluck('code')
            ->all();

        // Ordena por criticidade antes de agrupar -- groupBy() preserva a
        // ordem relativa dentro de cada grupo, entao isso deixa as O.S.
        // mais criticas no topo de cada coluna sem mudar a logica de
        // agrupamento.
        $orders = $orders->sortByDesc(fn (MaintenanceOrder $order) => static::criticalityRank($order, $codigosUrgentes))->values();

        return static::groupByStatus($orders, $this->viewMode);
    }

    /**
     * Nivel do ativo (via Matriz ABC) marcado como urgente > demais.
     * A criticidade e' do Ativo (Asset::abcMatrix), nao da O.S. --
     * MaintenanceOrder.criticality_level_id foi aposentado (ver
     * PainelCriticidade), nunca era preenchido por nenhuma tela real.
     */
    public static function criticalityRank(MaintenanceOrder $order, array $codigosUrgentes): int
    {
        $nivel = $order->asset?->abcMatrix?->nivel;

        return ($nivel && in_array($nivel, $codigosUrgentes, true)) ? 1 : 0;
    }

    public function getDaysInStage($record): int
    {
        $status = ($this->viewMode === 'oficina') ? $record->internal_status : ($record->commercial_status ?? 'oficina');

        $lastChange = $record->statusHistories()
            ->where('new_status', $status)
            ->latest('created_at')
            ->first();

        // Carbon 3: diffInDays() retorna float; o metodo declara -> int.
        return $lastChange ? (int) $lastChange->created_at->diffInDays(now(), true) : 0;
    }

    public function updateStatus($recordId, $newStatus)
    {
        $order = MaintenanceOrder::find($recordId);
        if ($order) {
            MaintenanceStatusHistory::create([
                'maintenance_order_id' => $recordId,
                'old_status' => ($this->viewMode === 'oficina') ? $order->internal_status : $order->commercial_status,
                'new_status' => $newStatus,
                'user_id' => auth()->id(),
                'created_at' => now(),
            ]);

            if ($this->viewMode === 'oficina') {
                $order->update(['internal_status' => $newStatus]);
            } else {
                $order->update(['commercial_status' => $newStatus]);
            }

            $this->dispatch('notify', ['message' => 'Status atualizado com sucesso!', 'type' => 'success']);
        }
    }

    public function toggleStatusVisibility(string $statusId): void
    {
        if (in_array($statusId, $this->hiddenStatuses, true)) {
            $this->hiddenStatuses = array_values(array_diff($this->hiddenStatuses, [$statusId]));
        } else {
            $this->hiddenStatuses[] = $statusId;
        }
    }

    public function clearFilters(): void
    {
        $this->technicianId = '';
        $this->hiddenStatuses = [];
    }

    public function toggleFiltersPanel(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function getActiveFilterCount(): int
    {
        return (! empty($this->technicianId) ? 1 : 0) + count($this->hiddenStatuses);
    }

    /**
     * Ativos com Solicitacao de Locacao pendente marcada como "Reservar para
     * Manutencao (Urgente)" (status_comercial = reserva_manutencao) -- o
     * Kanban destaca o card daquele Ativo pra avisar a oficina que existe
     * demanda comercial urgente esperando ele sair da manutencao.
     */
    public function getUrgentAssetIds(): array
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return [];
        }

        return SolicitacaoLocacao::assetIdsComReservaUrgente($tenant->id);
    }

    /**
     * Codigos de CriticalityLevel marcados como urgente pelo admin do tenant
     * -- usado pelo blade pra decidir o selo "criticidade alta" por card.
     */
    public function getCriticalidadeUrgenteCodigos(): array
    {
        return CriticalityLevel::where('tenant_id', Tenancy::current()?->id)
            ->where('is_urgent', true)
            ->pluck('code')
            ->all();
    }

    public function getPrintUrl(): string
    {
        return route('maintenance.kanban.print', array_filter([
            'mode' => $this->viewMode,
            'technician' => $this->technicianId ?: null,
            'hidden' => ! empty($this->hiddenStatuses) ? implode(',', $this->hiddenStatuses) : null,
            'search' => $this->search ?: null,
        ]));
    }
}
