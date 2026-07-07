<?php

namespace App\Livewire;

use App\Models\EquipmentDamage;
use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementItem;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.checklist-mobile')]
class EquipmentDamageMobile extends Component
{
    use WithFileUploads;

    public EquipmentMovement $equipmentMovement;

    #[Url(as: 'item')]
    public ?string $itemId = null;

    public ?EquipmentDamage $damage = null;

    public string $severity = '';

    public string $damageType = '';

    public string $description = '';

    public bool $requiresReplacement = false;

    public $newPhoto = null;

    public ?float $newPhotoLat = null;

    public ?float $newPhotoLng = null;

    public function mount(EquipmentMovement $equipmentMovement): void
    {
        Gate::authorize('view', $equipmentMovement->maintenanceOrder);
        Gate::authorize('create', EquipmentDamage::class);

        $this->equipmentMovement = $equipmentMovement;
    }

    public function getOriginItemProperty(): ?EquipmentMovementItem
    {
        if (! $this->itemId) {
            return null;
        }

        return $this->equipmentMovement->items()->whereKey($this->itemId)->first();
    }

    public function getIsLockedProperty(): bool
    {
        return (bool) $this->damage?->client_acknowledged_at;
    }

    public function getCanSignProperty(): bool
    {
        return $this->damage
            && ! $this->isLocked
            && $this->damage->getMedia('photos')->isNotEmpty();
    }

    public function saveDamageDetails(): void
    {
        if ($this->isLocked) {
            return;
        }

        $this->validate([
            'severity' => 'required|in:'.implode(',', [
                EquipmentDamage::SEVERITY_LEVE,
                EquipmentDamage::SEVERITY_MODERADA,
                EquipmentDamage::SEVERITY_GRAVE,
            ]),
            'damageType' => 'required|in:'.implode(',', [
                EquipmentDamage::DAMAGE_TYPE_HIDRAULICO,
                EquipmentDamage::DAMAGE_TYPE_ELETRICO,
                EquipmentDamage::DAMAGE_TYPE_PNEU_ESTEIRA,
                EquipmentDamage::DAMAGE_TYPE_MOTOR,
                EquipmentDamage::DAMAGE_TYPE_ESTRUTURAL,
                EquipmentDamage::DAMAGE_TYPE_OUTRO,
            ]),
            'description' => 'required|string|max:2000',
        ]);

        if ($this->damage) {
            Gate::authorize('update', $this->damage);

            $this->damage->update([
                'severity' => $this->severity,
                'damage_type' => $this->damageType,
                'description' => $this->description,
                'requires_replacement' => $this->requiresReplacement,
            ]);

            return;
        }

        $this->damage = EquipmentDamage::create([
            'equipment_movement_id' => $this->equipmentMovement->id,
            'equipment_movement_item_id' => $this->originItem?->id,
            'maintenance_order_id' => $this->equipmentMovement->maintenance_order_id,
            'asset_id' => $this->equipmentMovement->asset_id,
            'reported_by_user_id' => auth()->id(),
            'severity' => $this->severity,
            'damage_type' => $this->damageType,
            'description' => $this->description,
            'requires_replacement' => $this->requiresReplacement,
        ]);
    }

    public function savePhoto(): void
    {
        if (! $this->damage || $this->isLocked) {
            return;
        }

        Gate::authorize('update', $this->damage);

        $this->validate([
            'newPhoto' => 'required|image|max:5120',
            'newPhotoLat' => 'nullable|numeric|between:-90,90',
            'newPhotoLng' => 'nullable|numeric|between:-180,180',
        ]);

        $media = $this->damage->addMedia($this->newPhoto->getRealPath())
            ->usingFileName($this->newPhoto->getClientOriginalName())
            ->toMediaCollection('photos');

        if ($this->newPhotoLat !== null && $this->newPhotoLng !== null) {
            $media->setCustomProperty('latitude', $this->newPhotoLat);
            $media->setCustomProperty('longitude', $this->newPhotoLng);
            $media->setCustomProperty('captured_at', now()->toISOString());
            $media->save();
        }

        $this->reset('newPhoto', 'newPhotoLat', 'newPhotoLng');
    }

    public function removePhoto(int $mediaId): void
    {
        if (! $this->damage || $this->isLocked) {
            return;
        }

        Gate::authorize('update', $this->damage);

        $this->damage->media()->whereKey($mediaId)->firstOrFail()->delete();
    }

    public function saveSignature(string $signatureData): void
    {
        if (! $this->canSign) {
            return;
        }

        if (blank($signatureData) || ! str_starts_with($signatureData, 'data:image')) {
            $this->addError('signature', 'Assinatura inválida.');

            return;
        }

        Gate::authorize('update', $this->damage);

        $this->damage->update([
            'client_signature' => $signatureData,
            'client_acknowledged_at' => now(),
            'status' => EquipmentDamage::STATUS_AGUARDANDO_SUPERVISOR,
        ]);
    }

    public function back()
    {
        return redirect()->route('maintenance-orders.equipment-movement-mobile', [
            'maintenanceOrder' => $this->equipmentMovement->maintenance_order_id,
            'type' => $this->equipmentMovement->type,
        ]);
    }

    public function render()
    {
        return view('livewire.equipment-damage-mobile');
    }
}
