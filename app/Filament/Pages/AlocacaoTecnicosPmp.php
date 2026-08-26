<?php

namespace App\Filament\Pages;

use App\Models\MaintenanceDueAlert;
use App\Models\MaintenanceOrder;
use App\Models\TechnicianAllocation;
use App\Models\User;
use App\Support\Tenancy;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Fila de Alocação (itens em manutenção sem/com técnico) + Gantt (1 raia
 * por técnico) na MESMA página -- drag-and-drop da fila pro Gantt exige
 * que as duas fiquem no mesmo componente Livewire (decisão confirmada com
 * o usuário 2026-08-26, ver plano). Reaproveita PainelPmp::getPendingAlerts()
 * (já pública) pra fila em vez de duplicar a lógica de dedupe.
 */
class AlocacaoTecnicosPmp extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = 'PMP';

    protected static ?string $navigationLabel = 'Alocação de Técnicos';

    protected static ?string $title = 'Alocação de Técnicos';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.alocacao-tecnicos-pmp';

    public string $viewMode = 'week'; // 'day' | 'week' | 'month'

    public ?string $referenceDate = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    public function mount(): void
    {
        $this->referenceDate = now()->toDateString();
    }

    protected function referenceCarbon(): Carbon
    {
        return Carbon::parse($this->referenceDate ?? now());
    }

    public function periodBounds(): array
    {
        $ref = $this->referenceCarbon();

        return match ($this->viewMode) {
            'day' => [$ref->copy()->startOfDay(), $ref->copy()->endOfDay()],
            'month' => [$ref->copy()->startOfMonth(), $ref->copy()->endOfMonth()],
            default => [$ref->copy()->startOfWeek(), $ref->copy()->endOfWeek()],
        };
    }

    /**
     * Itens pendentes de alocação: reaproveita PainelPmp::getPendingAlerts()
     * (alertas "A Fazer" sem OS ainda) + OS corretivas abertas (que também
     * precisam de técnico, mas nunca aparecem no Kanban PMP porque ele só
     * mostra preventivas).
     */
    public function getQueueItemsProperty(): Collection
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return collect();
        }

        $alerts = (new PainelPmp)->getPendingAlerts()->map(fn (MaintenanceDueAlert $alert) => [
            'source_id' => 'alert:'.$alert->id,
            'title' => $alert->asset->name.' — '.$alert->maintenancePlan->name,
            'failure_category' => null,
            'criticality' => $alert->asset->currentCriticalityLevel(),
            'allocated' => false,
        ]);

        $corrective = MaintenanceOrder::where('tenant_id', $tenant->id)
            ->where('maintenance_type', MaintenanceOrder::TYPE_CORRECTIVE)
            ->where('internal_status', '!=', 'concluido')
            ->where('status', '!=', 'Cancelada')
            ->with('asset')
            ->get()
            ->filter(fn (MaintenanceOrder $order) => $order->asset)
            ->map(fn (MaintenanceOrder $order) => [
                'source_id' => 'os:'.$order->id,
                'title' => $order->asset->name.' — '.($order->description ? Str::limit($order->description, 40) : 'Corretiva'),
                'failure_category' => $order->failure_category,
                'criticality' => $order->asset->currentCriticalityLevel(),
                'allocated' => (bool) $order->technician_id,
            ]);

        return $alerts->concat($corrective)->values();
    }

    /**
     * Raias do Gantt: técnicos do tenant, filtrados por supervisão pra
     * quem não é admin (mesmo critério de AgendaTecnico::getTechniciansProperty()).
     */
    public function getTechniciansProperty(): Collection
    {
        $user = auth()->user();
        $query = User::where('tenant_id', Tenancy::current()?->id);

        if ($user && ! $user->isAdmin()) {
            $departmentIds = $user->supervisedDepartmentIds();
            $query->when($departmentIds, fn ($q) => $q->whereIn('department_id', $departmentIds));
        }

        return $query->orderBy('name')->get();
    }

    public function getAllocationsProperty(): Collection
    {
        [$start, $end] = $this->periodBounds();

        return TechnicianAllocation::whereBetween('starts_at', [$start, $end])
            ->with(['technician', 'maintenanceOrder.asset'])
            ->get();
    }

    /**
     * Cria/atualiza a alocação. Aviso (não bloqueio) se o técnico não tem a
     * especialidade correspondente ao failure_category de origem -- mesma
     * filosofia de MaintenanceOrderResource::technicianOptionsByWorkload().
     */
    public function allocate(string $sourceId, string $technicianId, string $startsAt): void
    {
        [$type, $id] = explode(':', $sourceId, 2);

        $technician = User::find($technicianId);
        if (! $technician) {
            return;
        }

        $starts = Carbon::parse($startsAt);
        $ends = $starts->copy()->addHours(2);

        $data = [
            'tenant_id' => Tenancy::current()?->id,
            'technician_id' => $technician->id,
            'starts_at' => $starts,
            'ends_at' => $ends,
        ];

        if ($type === 'os') {
            $order = MaintenanceOrder::find($id);
            if (! $order) {
                return;
            }
            $data['maintenance_order_id'] = $order->id;

            if ($order->failure_category && ! $technician->hasSpecialty($order->failure_category)) {
                Notification::make()
                    ->title('Técnico sem a especialidade sugerida')
                    ->body('A OS é de categoria "'.MaintenanceOrder::failureCategoryLabels()[$order->failure_category].'", mas '.$technician->name.' não tem essa especialidade cadastrada. Alocação feita mesmo assim.')
                    ->warning()
                    ->send();
            }
        } elseif ($type === 'alert') {
            $alert = MaintenanceDueAlert::find($id);
            if (! $alert) {
                return;
            }
            $data['maintenance_due_alert_id'] = $alert->id;
        } else {
            return;
        }

        TechnicianAllocation::create($data);

        Notification::make()->title('Alocação criada')->success()->send();
    }
}
