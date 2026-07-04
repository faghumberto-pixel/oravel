<?php

namespace App\Livewire;

use App\Models\MaintenanceOrder;
use App\Models\MaintenanceOrderChecklist;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Layout('layouts.checklist-mobile')]
class MaintenanceChecklistMobile extends Component
{
    use WithFileUploads;

    public MaintenanceOrder $maintenanceOrder;

    public ?string $expandedItemId = null;

    public string $newObservation = '';

    public $newPhoto = null;

    public function mount(MaintenanceOrder $maintenanceOrder): void
    {
        Gate::authorize('view', $maintenanceOrder);

        $this->maintenanceOrder = $maintenanceOrder;
    }

    public function getItemsProperty()
    {
        return $this->maintenanceOrder->checklists()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function getProgressProperty(): int
    {
        $items = $this->items;

        if ($items->isEmpty()) {
            return 0;
        }

        return (int) round($items->where('is_completed', true)->count() / $items->count() * 100);
    }

    public function toggleComplete(string $itemId): void
    {
        $item = $this->maintenanceOrder->checklists()->whereKey($itemId)->firstOrFail();
        $item->is_completed = ! $item->is_completed;
        $item->save();
    }

    public function expand(string $itemId): void
    {
        if ($this->expandedItemId === $itemId) {
            $this->collapse();

            return;
        }

        $item = $this->maintenanceOrder->checklists()->whereKey($itemId)->firstOrFail();

        $this->expandedItemId = $itemId;
        $this->newObservation = (string) $item->notes;
        $this->newPhoto = null;
    }

    public function collapse(): void
    {
        $this->expandedItemId = null;
        $this->newObservation = '';
        $this->newPhoto = null;
    }

    public function saveItemDetails(): void
    {
        if (! $this->expandedItemId) {
            return;
        }

        $this->validate([
            'newObservation' => 'nullable|string|max:2000',
            'newPhoto' => 'nullable|image|max:5120',
        ]);

        $item = $this->maintenanceOrder->checklists()->whereKey($this->expandedItemId)->firstOrFail();
        $item->notes = $this->newObservation;
        $item->save();

        if ($this->newPhoto) {
            $item->addMedia($this->newPhoto->getRealPath())
                ->usingFileName($this->newPhoto->getClientOriginalName())
                ->toMediaCollection('photos');
        }

        $this->collapse();
    }

    public function removeMedia(int $mediaId): void
    {
        Media::query()
            ->where('model_type', MaintenanceOrderChecklist::class)
            ->whereIn('model_id', $this->maintenanceOrder->checklists()->pluck('id'))
            ->findOrFail($mediaId)
            ->delete();
    }

    public function finalize()
    {
        if ($this->progress < 100) {
            return;
        }

        session()->flash('success', 'Checklist finalizado com sucesso.');

        return redirect()->route('filament.admin.resources.maintenance-orders.edit', ['record' => $this->maintenanceOrder]);
    }

    public function back()
    {
        return redirect()->route('filament.admin.resources.maintenance-orders.edit', ['record' => $this->maintenanceOrder]);
    }

    public function render()
    {
        return view('livewire.maintenance-checklist-mobile');
    }
}
