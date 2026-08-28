<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\Client;
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

    // Filtros de consulta -- pedido do usuário 2026-08-28: além de
    // dia/semana/mês, poder filtrar por cliente, técnico e patrimônio do
    // equipamento. Afetam o Gantt em tela E o conteúdo da impressão.
    public ?string $filterClientId = null;

    public ?string $filterTechnicianId = null;

    public ?string $filterPatrimonio = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', MaintenanceOrder::class);
    }

    public function mount(): void
    {
        $this->referenceDate = now()->toDateString();
    }

    /**
     * Lista de clientes pro select do filtro -- só os que têm algum ativo
     * vinculado (via Asset::client_id), pra não poluir o dropdown com
     * clientes sem equipamento.
     */
    public function getFilterClientOptionsProperty(): Collection
    {
        $clientIds = Asset::where('tenant_id', Tenancy::current()?->id)
            ->whereNotNull('client_id')
            ->distinct()
            ->pluck('client_id');

        return Client::whereIn('id', $clientIds)->orderBy('name')->pluck('name', 'id');
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

        if ($this->filterTechnicianId) {
            $query->where('id', $this->filterTechnicianId);
        }

        return $query->orderBy('name')->get();
    }

    /**
     * Resumo por técnico: alocados / aguardando confirmação (digital
     * pendente) / confirmados -- base tanto do indicador em tela quanto da
     * impressão. Reaproveita $this->allocations (já filtrado por
     * período+cliente+técnico+patrimônio) e $this->technicians (já
     * filtrado por supervisão+técnico), então o resumo respeita os mesmos
     * filtros aplicados ao Gantt.
     */
    public function getTechnicianSummaryProperty(): Collection
    {
        $byTechnician = $this->allocations->groupBy('technician_id');

        return $this->technicians->map(function (User $technician) use ($byTechnician) {
            $items = $byTechnician->get($technician->id, collect());

            $aguardando = $items->filter(fn (TechnicianAllocation $a) => $a->delivery_mode === TechnicianAllocation::DELIVERY_DIGITAL
                && $a->status === TechnicianAllocation::STATUS_PLANEJADO
            )->count();

            $confirmados = $items->filter(fn (TechnicianAllocation $a) => $a->status === TechnicianAllocation::STATUS_CONFIRMADO)->count();

            return [
                'technician' => $technician,
                'alocados' => $items->count(),
                'aguardando' => $aguardando,
                'confirmados' => $confirmados,
            ];
        })->values();
    }

    /**
     * Total consolidado (soma de todos os técnicos) -- vai no rodapé do
     * impresso.
     */
    public function getTechnicianSummaryTotalsProperty(): array
    {
        return [
            'alocados' => $this->technicianSummary->sum('alocados'),
            'aguardando' => $this->technicianSummary->sum('aguardando'),
            'confirmados' => $this->technicianSummary->sum('confirmados'),
        ];
    }

    /**
     * Monta a página em memória (sem HTTP) aplicando os mesmos filtros da
     * URL da tela, pra rota de impressão (routes/web.php) reaproveitar toda
     * a lógica de filtro/resumo já escrita aqui em vez de duplicá-la.
     */
    public static function forPrint(array $filters): self
    {
        $page = new self;
        $page->viewMode = $filters['viewMode'] ?? 'week';
        $page->referenceDate = $filters['referenceDate'] ?? now()->toDateString();
        $page->filterClientId = $filters['filterClientId'] ?? null;
        $page->filterTechnicianId = $filters['filterTechnicianId'] ?? null;
        $page->filterPatrimonio = $filters['filterPatrimonio'] ?? null;

        return $page;
    }

    public function getAllocationsProperty(): Collection
    {
        [$start, $end] = $this->periodBounds();

        $query = TechnicianAllocation::whereBetween('starts_at', [$start, $end])
            ->with([
                'technician',
                'maintenanceOrder.asset.client',
                'maintenanceOrder.maintenancePlan',
                'maintenanceOrder.client',
                'maintenanceDueAlert.asset.client',
                'maintenanceDueAlert.maintenancePlan',
            ]);

        if ($this->filterTechnicianId) {
            $query->where('technician_id', $this->filterTechnicianId);
        }

        $allocations = $query->get();

        if ($this->filterClientId) {
            $allocations = $allocations->filter(function (TechnicianAllocation $allocation) {
                $clientId = $allocation->maintenanceOrder?->client_id
                    ?? $allocation->maintenanceOrder?->asset?->client_id
                    ?? $allocation->maintenanceDueAlert?->asset?->client_id;

                return $clientId === $this->filterClientId;
            })->values();
        }

        if ($this->filterPatrimonio) {
            $needle = mb_strtolower($this->filterPatrimonio);
            $allocations = $allocations->filter(function (TechnicianAllocation $allocation) use ($needle) {
                $patrimonio = $allocation->maintenanceOrder?->asset?->patrimonio
                    ?? $allocation->maintenanceDueAlert?->asset?->patrimonio
                    ?? '';

                return str_contains(mb_strtolower($patrimonio), $needle);
            })->values();
        }

        return $allocations;
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

    /**
     * Pedido do usuário 2026-08-28: técnico que não usa o app recebe a OS
     * impressa -- o ato de imprimir já conta como entregue (sem passo de
     * aceite digital). whereNotNull() evita imprimir alocação que ainda
     * só tem MaintenanceDueAlert (OS ainda não existe).
     */
    public function printAllocation(string $allocationId): void
    {
        $allocation = TechnicianAllocation::whereNotNull('maintenance_order_id')->find($allocationId);
        if (! $allocation) {
            return;
        }

        $allocation->update([
            'delivery_mode' => TechnicianAllocation::DELIVERY_IMPRESSA,
            'status' => TechnicianAllocation::STATUS_CONFIRMADO,
        ]);
    }

    /**
     * Quantas alocações digitais (do período visível) ainda aguardam o
     * técnico confirmar pelo app -- impressa já nasce confirmada, não
     * entra nessa contagem.
     */
    public function getPendingDigitalCountProperty(): int
    {
        return $this->allocations
            ->where('delivery_mode', TechnicianAllocation::DELIVERY_DIGITAL)
            ->where('status', TechnicianAllocation::STATUS_PLANEJADO)
            ->count();
    }

    /**
     * Pedido do usuário 2026-08-28: o card do Gantt não mostrava opção de
     * confirmar, só o link de imprimir. Diferente de
     * TechnicianDailyTasks::confirmAllocation() (escopado por
     * Auth::id() === technician_id, pro próprio técnico confirmar), aqui
     * quem confirma é o analista vendo o Gantt -- por isso o escopo é a
     * permissão de gerenciar a alocação (mesmo gate de canAccess() desta
     * página), não a identidade do técnico.
     */
    public function confirmAllocation(string $allocationId): void
    {
        $allocation = TechnicianAllocation::find($allocationId);
        if (! $allocation) {
            return;
        }

        $allocation->update(['status' => TechnicianAllocation::STATUS_CONFIRMADO]);
    }
}
