<?php

namespace App\Filament\Pages;

use App\Models\PreventiveMaintenanceExecution;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PreventiveMaintenanceKanban extends Page
{
    protected static ?string $title = 'Kanban de Execuções Preventivas';

    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    protected static ?string $navigationGroup = 'PMP';
    protected static ?string $navigationLabel = 'Kanban Preventivas';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.preventive-maintenance-kanban';

    public static function getNavigationBadge(): ?string
    {
        return 'P M';
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'gray';
    }

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', PreventiveMaintenanceExecution::class);
    }

    public $search = '';

    public $technicianId = '';

    public $assetId = '';

    public $weekFilter = '';

    public $startDate = '';

    public $endDate = '';

    public $groupId = '';

    public $clientId = '';

    public array $hiddenStatuses = [];

    public bool $showFilters = false;

    public function getMaxContentWidth(): MaxWidth
    {
        return MaxWidth::Full;
    }

    public function getTotalExecutionsCount(): int
    {
        $tenant = Tenancy::current();
        if (!$tenant) {
            return 0;
        }

        return PreventiveMaintenanceExecution::where('tenant_id', $tenant->id)->count();
    }

    public function getRecords(): Collection
    {
        if (!Tenancy::current()) {
            return collect();
        }

        $query = $this->buildQuery();
        $executions = $query->get();

        return $this->groupByStatus($executions);
    }

    private function buildQuery(): Builder
    {
        $tenant = Tenancy::current();
        $query = PreventiveMaintenanceExecution::where('tenant_id', $tenant->id)
            ->with(['asset', 'maintenancePlan', 'maintenanceOrder', 'technician', 'asset.client']);

        if (!empty($this->search)) {
            $query->whereHas('asset', fn ($q) => $q->where('patrimonio', 'like', '%' . $this->search . '%')
                ->orWhere('name', 'like', '%' . $this->search . '%'));
        }

        if (!empty($this->technicianId)) {
            $query->where('technician_id', $this->technicianId);
        }

        if (!empty($this->assetId)) {
            $query->where('asset_id', $this->assetId);
        }

        if (!empty($this->groupId)) {
            $query->whereHas('asset', fn ($q) => $q->where('checklist_group_id', $this->groupId));
        }

        if (!empty($this->clientId)) {
            $query->whereHas('asset', fn ($q) => $q->where('client_id', $this->clientId));
        }

        if (!empty($this->startDate) && !empty($this->endDate)) {
            $query->whereBetween('created_at', [$this->startDate, $this->endDate . ' 23:59:59']);
        } elseif (!empty($this->weekFilter)) {
            [$startDate, $endDate] = $this->parseWeekFilter($this->weekFilter);
            $query->whereBetween('created_at', [$startDate, $endDate]);
        }

        return $query->latest('created_at');
    }

    private function parseWeekFilter(string $weekFilter): array
    {
        if (preg_match('/(\d{4})-W(\d{2})/', $weekFilter, $matches)) {
            $year = (int) $matches[1];
            $week = (int) $matches[2];
            $date = Carbon::setISODate($year, $week);

            return [
                $date->startOfWeek(),
                $date->endOfWeek(),
            ];
        }

        return [now()->startOfWeek(), now()->endOfWeek()];
    }

    private function groupByStatus(Collection $executions): Collection
    {
        $statuses = $this->getStatuses();

        return collect($statuses)
            ->mapWithKeys(fn ($status, $key) => [
                $key => $executions->filter(
                    fn ($execution) => strtolower($execution->maintenanceOrder?->internal_status ?? '') === strtolower($key)
                )->values(),
            ]);
    }

    public static function statusMap(): array
    {
        return [
            'aguardando_diagnostico' => ['title' => 'Aguardando Diagnóstico', 'color' => 'bg-slate-600'],
            'em_manutencao' => ['title' => 'Em Manutenção', 'color' => 'bg-blue-600'],
            'aguardando_peca' => ['title' => 'Aguardando Peça', 'color' => 'bg-amber-500'],
            'teste_qualidade' => ['title' => 'Teste de Qualidade', 'color' => 'bg-purple-600'],
            'pronto_giro' => ['title' => 'Pronto para Giro', 'color' => 'bg-teal-600'],
            'pendencia' => ['title' => 'Pendência', 'color' => 'bg-orange-500'],
            'concluido' => ['title' => 'Concluído', 'color' => 'bg-emerald-600'],
        ];
    }

    public function getStatuses(): array
    {
        return static::statusMap();
    }

    public function getVisibleStatuses(): array
    {
        return array_diff_key($this->getStatuses(), array_flip($this->hiddenStatuses));
    }

    public function getTechniciansList(): Collection
    {
        $tenant = Tenancy::current();
        if (!$tenant) {
            return collect();
        }

        return User::whereHas('preventiveMaintenanceExecutions', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    public function getAssetsList(): Collection
    {
        $tenant = Tenancy::current();
        if (!$tenant) {
            return collect();
        }

        return \App\Models\Asset::whereHas('preventiveMaintenanceExecutions', fn ($q) => $q->where('tenant_id', $tenant->id))
            ->select('id', 'patrimonio', 'name')
            ->orderBy('patrimonio')
            ->get();
    }

    public function getGroupsList(): Collection
    {
        $tenant = Tenancy::current();
        if (!$tenant) {
            return collect();
        }

        return \App\Models\ChecklistGroup::whereHas('assets', fn ($q) =>
            $q->whereHas('preventiveMaintenanceExecutions', fn ($q2) => $q2->where('tenant_id', $tenant->id))
        )
            ->where('tenant_id', $tenant->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    public function getClientsList(): Collection
    {
        $tenant = Tenancy::current();
        if (!$tenant) {
            return collect();
        }

        return \App\Models\Client::whereHas('assets', fn ($q) =>
            $q->whereHas('preventiveMaintenanceExecutions', fn ($q2) => $q2->where('tenant_id', $tenant->id))
        )
            ->where('tenant_id', $tenant->id)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
    }

    public function getActiveFilterCount(): int
    {
        $count = 0;
        if (!empty($this->search)) $count++;
        if (!empty($this->technicianId)) $count++;
        if (!empty($this->assetId)) $count++;
        if (!empty($this->groupId)) $count++;
        if (!empty($this->clientId)) $count++;
        if (!empty($this->startDate) || !empty($this->endDate)) $count++;
        if (!empty($this->weekFilter)) $count++;

        return $count;
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
        $this->search = '';
        $this->technicianId = '';
        $this->assetId = '';
        $this->weekFilter = '';
        $this->startDate = '';
        $this->endDate = '';
        $this->groupId = '';
        $this->clientId = '';
        $this->hiddenStatuses = [];
    }

    public function toggleFiltersPanel(): void
    {
        $this->showFilters = !$this->showFilters;
    }

    public function getPrintData(): array
    {
        $records = $this->getRecords();
        $statuses = $this->getStatuses();

        return [
            'records' => $records,
            'statuses' => $statuses,
            'filtros' => $this->getActiveFilterLabels(),
        ];
    }

    private function getActiveFilterLabels(): array
    {
        $labels = [];

        if (!empty($this->technicianId)) {
            $tech = User::find($this->technicianId);
            $labels[] = "Técnico: {$tech?->name}";
        }

        if (!empty($this->assetId)) {
            $asset = \App\Models\Asset::find($this->assetId);
            $labels[] = "Equipamento: {$asset?->patrimonio}";
        }

        if (!empty($this->groupId)) {
            $group = \App\Models\ChecklistGroup::find($this->groupId);
            $labels[] = "Grupo: {$group?->name}";
        }

        if (!empty($this->clientId)) {
            $client = \App\Models\Client::find($this->clientId);
            $labels[] = "Cliente: {$client?->name}";
        }

        if (!empty($this->startDate) && !empty($this->endDate)) {
            $labels[] = "Período: {$this->startDate} a {$this->endDate}";
        }

        return $labels;
    }
}
