<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\EquipmentPatioArrival;
use App\Models\EquipmentPatioArrivalItem;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Laudo de Recebimento -- checklist item a item da chegada FISICA no
 * patio, mesma mecanica de EquipmentMovementMobile (checklist de
 * mobilizacao/desmobilizacao), so' que pendurado em EquipmentPatioArrival
 * em vez de EquipmentMovement. So aplicavel a desmobilizacao concluida
 * (mesma regra que ja filtrava App\Filament\Pages\PatioChegadas).
 *
 * O ativo so' vira Asset::STATUS_DISPONIVEL quando o checklist chega a
 * 100% (finalize()) -- terminar o checklist de coleta no cliente (outro
 * checklist, em EquipmentMovementMobile) nunca fez isso sozinho, e aqui
 * continua a mesma logica: nem chegar no patio sozinho libera, precisa
 * do laudo completo.
 */
#[Layout('layouts.checklist-mobile')]
class EquipmentPatioArrivalMobile extends Component
{
    use WithFileUploads;

    public EquipmentMovement $equipmentMovement;

    public EquipmentPatioArrival $patioArrival;

    public ?string $expandedItemId = null;

    public string $newObservation = '';

    public bool $newHasDamage = false;

    public ?string $newResult = null;

    public $newPhoto = null;

    public ?float $newPhotoLat = null;

    public ?float $newPhotoLng = null;

    public string $generalNotes = '';

    public function mount(EquipmentMovement $equipmentMovement): void
    {
        Gate::authorize('view', $equipmentMovement);

        abort_unless(
            $equipmentMovement->type === EquipmentMovement::TYPE_DESMOBILIZACAO
                && $equipmentMovement->status === EquipmentMovement::STATUS_CONCLUIDO,
            404
        );

        $existing = EquipmentPatioArrival::where('equipment_movement_id', $equipmentMovement->id)->first();

        if ($existing) {
            $this->patioArrival = $existing;
        } else {
            Gate::authorize('create', EquipmentMovement::class);
            $this->patioArrival = EquipmentPatioArrival::create([
                'equipment_movement_id' => $equipmentMovement->id,
                'arrived_at' => now(),
                'confirmed_by_user_id' => auth()->id(),
            ]);
        }

        $this->equipmentMovement = $equipmentMovement;
        $this->generalNotes = (string) $this->patioArrival->initial_condition_notes;
    }

    public function getItemsProperty()
    {
        return $this->patioArrival->items()->orderBy('sort_order')->get();
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
        $item = $this->patioArrival->items()->whereKey($itemId)->firstOrFail();

        if (! $item->is_checked && $item->requires_photo && $item->getMedia('photos')->isEmpty()) {
            $this->addError('photoRequired', "Anexe uma foto antes de marcar \"{$item->label}\" como concluído.");

            return;
        }

        $item->is_checked = ! $item->is_checked;
        $item->save();
    }

    public function expand(string $itemId): void
    {
        if ($this->expandedItemId === $itemId) {
            $this->collapse();

            return;
        }

        $item = $this->patioArrival->items()->whereKey($itemId)->firstOrFail();

        $this->expandedItemId = $itemId;
        $this->newObservation = (string) $item->notes;
        $this->newHasDamage = (bool) $item->has_damage;
        $this->newResult = $item->result;
        $this->resetPhotoForm();
    }

    public function setResult(string $result): void
    {
        $this->newResult = $this->newResult === $result ? null : $result;
    }

    public function collapse(): void
    {
        $this->expandedItemId = null;
        $this->newObservation = '';
        $this->newHasDamage = false;
        $this->newResult = null;
        $this->resetPhotoForm();
    }

    public function saveItemDetails(): void
    {
        if (! $this->expandedItemId) {
            return;
        }

        $this->validate([
            'newObservation' => 'nullable|string|max:2000',
            'newHasDamage' => 'boolean',
            'newPhoto' => 'nullable|image|max:5120',
            'newPhotoLat' => 'nullable|numeric|between:-90,90',
            'newPhotoLng' => 'nullable|numeric|between:-180,180',
            'newResult' => 'nullable|in:'.implode(',', array_keys(EquipmentPatioArrivalItem::resultLabels())),
        ]);

        $item = $this->patioArrival->items()->whereKey($this->expandedItemId)->firstOrFail();
        $item->notes = $this->newObservation;
        $item->has_damage = $this->newHasDamage;
        $item->result = $this->newResult;
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

        $this->collapse();
    }

    public function removeMedia(int $mediaId): void
    {
        $itemIds = $this->patioArrival->items()->pluck('id');

        Media::query()
            ->where('model_type', EquipmentPatioArrivalItem::class)
            ->whereIn('model_id', $itemIds)
            ->findOrFail($mediaId)
            ->delete();
    }

    public function saveGeneralNotes(): void
    {
        $this->validate(['generalNotes' => 'nullable|string|max:2000']);

        $this->patioArrival->update(['initial_condition_notes' => $this->generalNotes ?: null]);
    }

    /**
     * Checklist 100% com algum item marcado has_damage: ativo vai pra
     * Quarentena em vez de Disponivel direto -- so' sai de la' pelo botao
     * dedicado "Liberar da Quarentena" (AssetResource), nao automaticamente.
     * Sem avaria encontrada, comportamento igual ao de sempre.
     */
    public function finalize()
    {
        if ($this->progress < 100) {
            return;
        }

        $this->patioArrival->update(['completed_at' => now()]);

        $foundDamage = $this->patioArrival->items()->where('has_damage', true)->exists();
        $goToQuarantine = $foundDamage && (Tenancy::current()?->hasModuleEnabled('quarentena') ?? true);

        $this->equipmentMovement->asset?->update([
            'status' => $goToQuarantine ? Asset::STATUS_QUARENTENA : Asset::STATUS_DISPONIVEL,
        ]);

        session()->flash('success', $goToQuarantine
            ? 'Laudo de Recebimento finalizado -- avaria encontrada, ativo em quarentena até revisão.'
            : 'Laudo de Recebimento finalizado -- ativo liberado como disponível.');

        return redirect()->route('filament.admin.pages.patio-chegadas');
    }

    public function back()
    {
        return redirect()->route('filament.admin.pages.patio-chegadas');
    }

    private function resetPhotoForm(): void
    {
        $this->newPhoto = null;
        $this->newPhotoLat = null;
        $this->newPhotoLng = null;
    }

    public function render()
    {
        return view('livewire.equipment-patio-arrival-mobile');
    }
}
