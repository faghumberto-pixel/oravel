<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementItem;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Layout('layouts.checklist-mobile')]
class EquipmentMovementMobile extends Component
{
    use WithFileUploads;

    public MaintenanceOrder $maintenanceOrder;

    public EquipmentMovement $equipmentMovement;

    public ?string $expandedItemId = null;

    public string $newObservation = '';

    public $newPhoto = null;

    public ?float $newPhotoLat = null;

    public ?float $newPhotoLng = null;

    public $newCoverPhoto = null;

    public ?float $coverPhotoLat = null;

    public ?float $coverPhotoLng = null;

    public function mount(MaintenanceOrder $maintenanceOrder, string $type): void
    {
        Gate::authorize('view', $maintenanceOrder);

        abort_unless(
            in_array($type, [EquipmentMovement::TYPE_MOBILIZACAO, EquipmentMovement::TYPE_DESMOBILIZACAO], true),
            404
        );

        $existing = EquipmentMovement::where('maintenance_order_id', $maintenanceOrder->id)
            ->where('type', $type)
            ->where('status', '!=', EquipmentMovement::STATUS_CONCLUIDO)
            ->latest()
            ->first();

        if ($existing) {
            Gate::authorize('view', $existing);
            $this->equipmentMovement = $existing;
        } else {
            Gate::authorize('create', EquipmentMovement::class);
            $this->equipmentMovement = EquipmentMovement::create([
                'maintenance_order_id' => $maintenanceOrder->id,
                'asset_id' => $maintenanceOrder->asset_id,
                'type' => $type,
                'status' => EquipmentMovement::STATUS_AGUARDANDO_VISTORIA,
            ]);

            // Ativo "acabou de retornar de obra" -- fica fora do pool
            // disponivel ate o patio concluir o checklist de retorno
            // (ver finalize()). So aqui, nao no Observer generico: os
            // movements criados internamente pelo fluxo de Troca de
            // Equipamento (EquipmentReplacement::startLogisticsMovements())
            // gerenciam o status do Ativo com suas proprias regras.
            if ($type === EquipmentMovement::TYPE_DESMOBILIZACAO) {
                $maintenanceOrder->asset?->update(['status' => Asset::STATUS_AGUARDANDO_TRIAGEM]);
            }
        }

        $this->maintenanceOrder = $maintenanceOrder;
    }

    public function getItemsProperty()
    {
        return $this->equipmentMovement->items()->orderBy('sort_order')->get();
    }

    public function getProgressProperty(): int
    {
        $items = $this->items;

        if ($items->isEmpty()) {
            return 0;
        }

        return (int) round($items->where('is_checked', true)->count() / $items->count() * 100);
    }

    public function toggleItem(string $itemId): void
    {
        $item = $this->equipmentMovement->items()->whereKey($itemId)->firstOrFail();

        if (! $item->is_checked && $item->requires_photo && $item->getMedia('photos')->isEmpty()) {
            $this->addError('photoRequired', "Anexe uma foto antes de marcar \"{$item->label}\" como concluído.");

            return;
        }

        $item->is_checked = ! $item->is_checked;
        $item->save();

        $this->markStarted();
    }

    public function expand(string $itemId): void
    {
        if ($this->expandedItemId === $itemId) {
            $this->collapse();

            return;
        }

        $item = $this->equipmentMovement->items()->whereKey($itemId)->firstOrFail();

        $this->expandedItemId = $itemId;
        $this->newObservation = (string) $item->notes;
        $this->resetPhotoForm();
    }

    public function collapse(): void
    {
        $this->expandedItemId = null;
        $this->newObservation = '';
        $this->resetPhotoForm();
    }

    public function saveItemDetails(): void
    {
        if (! $this->expandedItemId) {
            return;
        }

        $this->validate([
            'newObservation' => 'nullable|string|max:2000',
            'newPhoto' => 'nullable|image|max:5120',
            'newPhotoLat' => 'nullable|numeric|between:-90,90',
            'newPhotoLng' => 'nullable|numeric|between:-180,180',
        ]);

        $item = $this->equipmentMovement->items()->whereKey($this->expandedItemId)->firstOrFail();
        $item->notes = $this->newObservation;
        $item->save();

        if ($this->newPhoto) {
            $media = $item->addMedia($this->newPhoto->getRealPath())
                ->usingFileName($this->newPhoto->getClientOriginalName())
                ->toMediaCollection('photos');

            if ($this->newPhotoLat !== null && $this->newPhotoLng !== null) {
                $media->setCustomProperty('latitude', $this->newPhotoLat);
                $media->setCustomProperty('longitude', $this->newPhotoLng);
                $media->setCustomProperty('captured_at', now()->toISOString());
                $media->save();
            }
        }

        $this->markStarted();
        $this->collapse();
    }

    public function removeMedia(int $mediaId): void
    {
        $itemIds = $this->equipmentMovement->items()->pluck('id');

        Media::query()
            ->where('model_type', EquipmentMovementItem::class)
            ->whereIn('model_id', $itemIds)
            ->findOrFail($mediaId)
            ->delete();
    }

    public function saveVistoriaGeral(): void
    {
        $this->validate([
            'newCoverPhoto' => 'required|image|max:5120',
            'coverPhotoLat' => 'nullable|numeric|between:-90,90',
            'coverPhotoLng' => 'nullable|numeric|between:-180,180',
        ]);

        $this->equipmentMovement->addMedia($this->newCoverPhoto->getRealPath())
            ->usingFileName($this->newCoverPhoto->getClientOriginalName())
            ->toMediaCollection('vistoria_geral');

        $this->equipmentMovement->update([
            'vistoria_geral_lat' => $this->coverPhotoLat,
            'vistoria_geral_lng' => $this->coverPhotoLng,
            'vistoria_geral_captured_at' => now(),
        ]);

        $this->markStarted();

        $this->newCoverPhoto = null;
        $this->coverPhotoLat = null;
        $this->coverPhotoLng = null;
    }

    public function finalize()
    {
        if ($this->progress < 100) {
            return;
        }

        $this->equipmentMovement->update([
            'status' => EquipmentMovement::STATUS_CONCLUIDO,
            'completed_at' => now(),
        ]);

        // Patio confirmou o checklist de retorno (revisao de retorno) --
        // ativo sai de "aguardando triagem" e volta pro pool disponivel pro
        // comercial ver na hora, sem precisar de edicao manual.
        if ($this->equipmentMovement->type === EquipmentMovement::TYPE_DESMOBILIZACAO) {
            $this->maintenanceOrder->asset?->update(['status' => Asset::STATUS_DISPONIVEL]);
        }

        session()->flash('success', 'Movimentação finalizada com sucesso.');

        return redirect()->route('filament.admin.resources.maintenance-orders.edit', ['record' => $this->maintenanceOrder]);
    }

    public function back()
    {
        return redirect()->route('filament.admin.resources.maintenance-orders.edit', ['record' => $this->maintenanceOrder]);
    }

    private function markStarted(): void
    {
        if ($this->equipmentMovement->status === EquipmentMovement::STATUS_AGUARDANDO_VISTORIA) {
            $this->equipmentMovement->update([
                'status' => EquipmentMovement::STATUS_EM_ANDAMENTO,
                'started_at' => $this->equipmentMovement->started_at ?? now(),
            ]);
        }
    }

    private function resetPhotoForm(): void
    {
        $this->newPhoto = null;
        $this->newPhotoLat = null;
        $this->newPhotoLng = null;
    }

    public function render()
    {
        return view('livewire.equipment-movement-mobile');
    }
}
