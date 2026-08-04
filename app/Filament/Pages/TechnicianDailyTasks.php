<?php

namespace App\Filament\Pages;

use App\Models\AssetMovement;
use App\Models\MaintenanceOrder;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;

/**
 * "Minhas Ordens de Serviço" — lista do dia do técnico de campo.
 * Unifica três tipos de tarefa: Manutenção, Mobilização, Desmobilização.
 * Filtros: CEP, Criticidade ABC, Grupo de equipamento, Capacidade, Patrimônio, Tipo, Natureza (Interno/Externo).
 * Otimizado para mobile (375-430px) e offline-first (pré-carregado em IndexedDB).
 */
#[Layout('layouts.checklist-mobile')]
class TechnicianDailyTasks extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Manutenção';

    protected static ?string $title = 'Minhas Ordens de Serviço';

    protected static string $view = 'filament.pages.technician-daily-tasks';

    // Sort baixo (mesmo estilo de PainelGestao::$navigationSort = -10) --
    // e' o destino padrao do tecnico (redirect de /dashboard, ver
    // routes/web.php), e o RedirectToHomeController do Filament manda
    // /admin pra primeira pagina navegavel por ordem de sort. Sem isso,
    // qualquer NavigationItem sem sort definido (ex: "Registrar Horímetro")
    // podia acabar competindo e virando o destino de fato.
    protected static ?int $navigationSort = -9;

    public static function canAccess(): bool
    {
        return (bool) auth()->user();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) auth()->user();
    }

    // Filtros
    #[Url]
    public string $filterType = ''; // 'maintenance' | 'mobilization' | 'demobilization' | ''

    #[Url]
    public string $filterCriticality = ''; // 'A' | 'B' | 'C' | ''

    #[Url]
    public string $filterNature = ''; // 'internal' | 'external' | ''

    #[Url]
    public string $filterCEP = ''; // CEP para filtrar por localização

    #[Url]
    public string $sortBy = 'criticality'; // 'criticality' | 'cep' | 'created_at'

    #[Url]
    public string $search = ''; // busca livre por patrimônio/nome do ativo

    // Aberta = tarefas pendentes (comportamento original desta pagina).
    // Encerrada = O.S. de manutencao concluidas pelo tecnico -- pedido
    // explicito do usuario 2026-08-04 pra virar o destino padrao do
    // tecnico no lugar do Dashboard, sem grafico, so o essencial.
    #[Url]
    public string $activeTab = 'aberta'; // 'aberta' | 'encerrada'

    public function getTechnicianTasksProperty(): Collection
    {
        $userId = Auth::id();

        // Carregar todas as três categorias de tarefas do técnico
        $tasks = collect();

        // 1. Ordens de manutenção não concluídas
        $maintenanceOrders = MaintenanceOrder::query()
            ->where('technician_id', $userId)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereNotIn('status', ['Concluída', 'Cancelada'])
            ->with(['asset', 'asset.abcMatrix'])
            ->get()
            ->map(fn (MaintenanceOrder $order) => [
                'id' => $order->id,
                'type' => 'maintenance',
                'label' => 'MANUTENÇÃO',
                'patrimonio' => $order->asset?->patrimonio ?? '—',
                'asset_name' => $order->asset?->name ?? 'Sem ativo',
                'client' => $order->client?->name ?? null,
                'criticality' => $order->asset?->currentCriticalityLevel()?->letter ?? 'C',
                'nature' => $order->natureza_do_servico ?? 'Interno',
                'cep' => $order->asset?->cep ?? null,
                'status' => $order->status,
                'created_at' => $order->created_at,
                'maintenance_type' => $order->maintenance_type,
                'sla_target_minutes' => $order->sla_target_minutes,
                'sla_color' => $order->slaColor(),
                'replacement_status' => $order->equipmentReplacements?->first()?->status,
                'url' => route('maintenance-orders.field-wizard', $order),
            ]);

        // 2. Mobilizações pendentes
        $mobilizations = AssetMovement::query()
            ->where('technician_id', $userId)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('movement_type', 'mobilization')
            ->where('sync_status', '!=', 'synced')
            ->with(['asset', 'asset.abcMatrix', 'contract'])
            ->get()
            ->map(fn (AssetMovement $movement) => [
                'id' => $movement->id,
                'type' => 'mobilization',
                'label' => 'MOBILIZAÇÃO',
                'patrimonio' => $movement->asset?->patrimonio ?? '—',
                'asset_name' => $movement->asset?->name ?? 'Sem ativo',
                'client' => $movement->contract?->client?->name ?? null,
                'criticality' => $movement->asset?->currentCriticalityLevel()?->letter ?? 'C',
                'nature' => 'Externo',
                'cep' => $movement->contract?->client?->cep ?? null,
                'status' => $movement->sync_status,
                'created_at' => $movement->created_at,
                'url' => route('asset-movements.mobilization', $movement),
            ]);

        // 3. Desmobilizações pendentes
        $demobilizations = AssetMovement::query()
            ->where('technician_id', $userId)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('movement_type', 'demobilization')
            ->where('sync_status', '!=', 'synced')
            ->with(['asset', 'asset.abcMatrix', 'contract'])
            ->get()
            ->map(fn (AssetMovement $movement) => [
                'id' => $movement->id,
                'type' => 'demobilization',
                'label' => 'DESMOBILIZAÇÃO',
                'patrimonio' => $movement->asset?->patrimonio ?? '—',
                'asset_name' => $movement->asset?->name ?? 'Sem ativo',
                'client' => $movement->contract?->client?->name ?? null,
                'criticality' => $movement->asset?->currentCriticalityLevel()?->letter ?? 'C',
                'nature' => 'Retorno',
                'cep' => $movement->contract?->client?->cep ?? null,
                'status' => $movement->sync_status,
                'created_at' => $movement->created_at,
                'url' => route('asset-movements.demobilization', $movement),
            ]);

        $tasks = $maintenanceOrders->concat($mobilizations)->concat($demobilizations);

        // Aplicar filtros
        if ($this->filterType) {
            $tasks = $tasks->filter(fn ($task) => $task['type'] === $this->filterType);
        }

        if ($this->filterCriticality) {
            $tasks = $tasks->filter(fn ($task) => $task['criticality'] === $this->filterCriticality);
        }

        if ($this->filterNature) {
            $tasks = $tasks->filter(fn ($task) => match ($this->filterNature) {
                'internal' => $task['nature'] === 'Interno',
                'external' => in_array($task['nature'], ['Externo', 'Retorno']),
                default => true,
            });
        }

        if ($this->filterCEP) {
            $tasks = $tasks->filter(fn ($task) => str_starts_with($task['cep'] ?? '', $this->filterCEP));
        }

        if ($this->search) {
            $search = strtolower($this->search);
            $tasks = $tasks->filter(fn ($task) => str_contains(strtolower($task['patrimonio']), $search) ||
                str_contains(strtolower($task['asset_name']), $search)
            );
        }

        // Aplicar ordenação
        $tasks = match ($this->sortBy) {
            'cep' => $tasks->sortBy(fn ($t) => $t['cep'] ?? '~'),
            'created_at' => $tasks->sortByDesc('created_at'),
            'criticality' => $tasks->sortBy(fn ($t) => ['A' => 1, 'B' => 2, 'C' => 3][$t['criticality']] ?? 4),
            default => $tasks->sortBy(fn ($t) => ['A' => 1, 'B' => 2, 'C' => 3][$t['criticality']] ?? 4)->sortBy(fn ($t) => $t['cep'] ?? '~'),
        };

        return $tasks;
    }

    public function getPendingCountProperty(): int
    {
        return $this->technicianTasks->filter(fn ($t) => $t['status'] !== 'synced')->count();
    }

    public function getCompletedCountProperty(): int
    {
        return $this->technicianTasks->filter(fn ($t) => $t['status'] === 'synced')->count();
    }

    /**
     * O.S. de manutenção encerradas (status Concluída) pelo técnico --
     * mobilização/desmobilização não entram aqui: uma vez sincronizadas
     * elas não têm mais uma noção equivalente de "encerrada" que o
     * técnico precise revisitar, diferente da O.S. em si.
     */
    public function getClosedTasksProperty(): Collection
    {
        $tasks = MaintenanceOrder::query()
            ->where('technician_id', Auth::id())
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('status', 'Concluída')
            ->with(['asset', 'asset.abcMatrix'])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (MaintenanceOrder $order) => [
                'id' => $order->id,
                'type' => 'maintenance',
                'label' => 'MANUTENÇÃO',
                'patrimonio' => $order->asset?->patrimonio ?? '—',
                'asset_name' => $order->asset?->name ?? 'Sem ativo',
                'client' => $order->client?->name ?? null,
                'criticality' => $order->asset?->currentCriticalityLevel()?->letter ?? 'C',
                'nature' => $order->natureza_do_servico ?? 'Interno',
                'cep' => $order->asset?->cep ?? null,
                'status' => $order->status,
                'created_at' => $order->created_at,
                'closed_at' => $order->updated_at,
                'maintenance_type' => $order->maintenance_type,
                'url' => route('maintenance-orders.field-wizard', $order),
            ]);

        if ($this->search) {
            $search = strtolower($this->search);
            $tasks = $tasks->filter(fn ($task) => str_contains(strtolower($task['patrimonio']), $search) ||
                str_contains(strtolower($task['asset_name']), $search)
            );
        }

        return $tasks;
    }
}
