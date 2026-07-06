<?php

namespace App\Livewire;

use App\Models\MaintenanceOrder;
use App\Models\MaintenancePlan;
use App\Models\PreventiveMaintenanceExecution;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Versao pro campo (celular do tecnico) do Plano de Manutencao Preventiva --
 * lista os itens do template do Grupo do Ativo com checkbox simples (fez/nao
 * fez), mostrando vencido/proximo por horimetro. Cada item marcado vira um
 * PreventiveMaintenanceExecution de verdade (nao um boolean solto -- por
 * isso "desmarcar" apaga o registro em vez de so virar false).
 */
#[Layout('layouts.checklist-mobile')]
class PreventiveMaintenanceMobile extends Component
{
    use WithFileUploads;

    public MaintenanceOrder $maintenanceOrder;

    public ?float $horimetro = null;

    public ?string $expandedPlanId = null;

    public string $newObservation = '';

    public $newPhoto = null;

    public function mount(MaintenanceOrder $maintenanceOrder): void
    {
        Gate::authorize('view', $maintenanceOrder);

        abort_unless((bool) $maintenanceOrder->asset?->checklist_group_id, 404);

        $this->maintenanceOrder = $maintenanceOrder;
        $this->horimetro = (float) $maintenanceOrder->asset->horimetro_atual;
    }

    public function getAssetProperty()
    {
        return $this->maintenanceOrder->asset;
    }

    public function getExecutionsProperty()
    {
        return PreventiveMaintenanceExecution::where('maintenance_order_id', $this->maintenanceOrder->id)
            ->get()
            ->keyBy('maintenance_plan_id');
    }

    /**
     * @return array<int, array{plan: MaintenancePlan, execution: ?PreventiveMaintenanceExecution, status: array}>
     */
    public function getItemsProperty(): array
    {
        $asset = $this->asset;
        $executions = $this->executions;

        return MaintenancePlan::where('checklist_group_id', $asset->checklist_group_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (MaintenancePlan $plan) => [
                'plan' => $plan,
                'execution' => $executions->get($plan->id),
                'status' => $plan->dueStatusForAsset($asset),
            ])
            ->all();
    }

    public function getProgressProperty(): int
    {
        $items = $this->items;

        if (empty($items)) {
            return 0;
        }

        $done = collect($items)->filter(fn ($item) => $item['execution'] !== null)->count();

        return (int) round($done / count($items) * 100);
    }

    /**
     * Consulta direto (nao via $this->executions) de proposito: a
     * computed property e cacheada pro resto da requisicao assim que
     * acessada, e essa mesma requisicao termina renderizando a view --
     * se o cache fosse preenchido aqui ANTES do create()/delete(), o
     * render() reaproveitaria o valor velho e a tela nunca refletiria a
     * mudanca (progress ficava sempre parado no valor anterior).
     */
    public function toggleItem(string $planId): void
    {
        $existing = PreventiveMaintenanceExecution::where('maintenance_order_id', $this->maintenanceOrder->id)
            ->where('maintenance_plan_id', $planId)
            ->first();

        if ($existing) {
            $existing->delete();

            return;
        }

        $this->validate(['horimetro' => 'required|numeric|min:0']);

        PreventiveMaintenanceExecution::create([
            'tenant_id' => $this->maintenanceOrder->tenant_id,
            'asset_id' => $this->maintenanceOrder->asset_id,
            'maintenance_plan_id' => $planId,
            'maintenance_order_id' => $this->maintenanceOrder->id,
            'technician_id' => auth()->id(),
            'horimetro_at_execution' => $this->horimetro,
        ]);
    }

    public function expand(string $planId): void
    {
        if ($this->expandedPlanId === $planId) {
            $this->collapse();

            return;
        }

        $execution = $this->executions->get($planId);

        if (! $execution) {
            return;
        }

        $this->expandedPlanId = $planId;
        $this->newObservation = (string) $execution->observacao;
        $this->newPhoto = null;
    }

    public function collapse(): void
    {
        $this->expandedPlanId = null;
        $this->newObservation = '';
        $this->newPhoto = null;
    }

    public function saveItemDetails(): void
    {
        if (! $this->expandedPlanId) {
            return;
        }

        $this->validate([
            'newObservation' => 'nullable|string|max:2000',
            'newPhoto' => 'nullable|image|max:5120',
        ]);

        $execution = $this->executions->get($this->expandedPlanId);

        if (! $execution) {
            return;
        }

        $execution->observacao = $this->newObservation;
        $execution->save();

        if ($this->newPhoto) {
            $execution->addMedia($this->newPhoto->getRealPath())
                ->usingFileName($this->newPhoto->getClientOriginalName())
                ->toMediaCollection('photos');
        }

        $this->collapse();
    }

    public function removeMedia(int $mediaId): void
    {
        Media::query()
            ->where('model_type', PreventiveMaintenanceExecution::class)
            ->whereIn('model_id', $this->executions->pluck('id'))
            ->findOrFail($mediaId)
            ->delete();
    }

    public function back()
    {
        return redirect()->route('filament.admin.resources.maintenance-orders.edit', ['record' => $this->maintenanceOrder]);
    }

    public function render()
    {
        return view('livewire.preventive-maintenance-mobile');
    }
}
