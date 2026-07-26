<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Models\InternalUnit;
use App\Models\Material;
use App\Models\StorageLocation;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Componente unico de planta baixa (grid de quadrantes), reutilizado tanto
 * pelo almoxarifado (localizar peca) quanto pelo patio de ativos (localizar
 * equipamento por unidade) -- ver PlantaBaixaAlmoxarifado/PlantaBaixaPatioAtivos,
 * as duas Pages finas que so' escolhem o $context.
 */
class PlantaBaixaGrid extends Component
{
    public string $context;

    public ?string $internalUnitId = null;

    public string $colorMode = 'status'; // 'status' | 'criticidade' -- so' relevante pro context patio_ativos

    public ?string $statusFilter = null;

    public ?string $capacityMin = null;

    public ?string $capacityMax = null;

    public ?string $patrimonioSearch = null;

    public ?string $selectedLocationId = null;

    public function mount(string $context, ?string $internalUnitId = null): void
    {
        $this->context = $context;
        $this->internalUnitId = $internalUnitId ?? InternalUnit::query()->orderBy('name')->value('id');
    }

    public function getInternalUnitsProperty(): Collection
    {
        return InternalUnit::query()->orderBy('name')->get();
    }

    public function getLocationsProperty(): Collection
    {
        if (! $this->internalUnitId) {
            return collect();
        }

        return StorageLocation::query()
            ->where('context', $this->context)
            ->where('internal_unit_id', $this->internalUnitId)
            ->where('is_active', true)
            ->get();
    }

    /**
     * @return array<string, Collection>
     */
    public function getOccupantsByLocationProperty(): array
    {
        if ($this->locations->isEmpty()) {
            return [];
        }

        $locationIds = $this->locations->pluck('id');

        if ($this->context === StorageLocation::CONTEXT_ALMOXARIFADO) {
            return Material::query()
                ->whereIn('storage_location_id', $locationIds)
                ->with(['locationStocks' => fn ($q) => $q->where('internal_unit_id', $this->internalUnitId)])
                ->get()
                ->groupBy('storage_location_id')
                ->all();
        }

        return $this->filteredAssetsQuery()
            ->whereIn('storage_location_id', $locationIds)
            ->get()
            ->groupBy('storage_location_id')
            ->all();
    }

    private function filteredAssetsQuery()
    {
        return Asset::query()
            ->with('abcMatrix')
            ->when($this->statusFilter, fn ($q, $status) => $q->where('status', $status))
            ->when($this->capacityMin, fn ($q, $min) => $q->where('capacity_value', '>=', $min))
            ->when($this->capacityMax, fn ($q, $max) => $q->where('capacity_value', '<=', $max))
            ->when($this->patrimonioSearch, fn ($q, $term) => $q->where(function ($q2) use ($term) {
                $q2->where('patrimonio', 'like', "%{$term}%")->orWhere('tag', 'like', "%{$term}%");
            }));
    }

    /**
     * Cor de um quadrante -- materiais por nivel de estoque agregado,
     * ativos por status (default) ou por criticidade (Matriz ABC).
     */
    public function cellColor(string $locationId): string
    {
        $occupants = $this->occupantsByLocation[$locationId] ?? null;

        if (! $occupants || $occupants->isEmpty()) {
            return 'gray';
        }

        if ($this->context === StorageLocation::CONTEXT_ALMOXARIFADO) {
            $hasEsgotado = $occupants->contains(fn (Material $m) => (int) ($m->locationStocks->first()?->current_quantity ?? 0) <= 0);
            if ($hasEsgotado) {
                return 'danger';
            }

            $hasBaixo = $occupants->contains(fn (Material $m) => $m->locationStocks->first()?->isLowStock() ?? false);

            return $hasBaixo ? 'warning' : 'success';
        }

        /** @var Asset $asset */
        $asset = $occupants->first();

        if ($this->colorMode === 'criticidade') {
            return $asset->currentCriticalityLevel() ? 'criticidade' : 'gray';
        }

        return Asset::statusColor($asset->status);
    }

    /**
     * Hex real da cor de criticidade (quando cellColor() retorna
     * 'criticidade') -- resolvido a parte porque vem do banco
     * (CriticalityLevel.color), nao de um nome fixo do Filament.
     */
    public function cellCriticalityHex(string $locationId): ?string
    {
        $occupants = $this->occupantsByLocation[$locationId] ?? null;
        /** @var ?Asset $asset */
        $asset = $occupants?->first();

        return $asset?->currentCriticalityLevel()?->color;
    }

    public function selectLocation(string $locationId): void
    {
        $this->selectedLocationId = $locationId;
    }

    public function closeModal(): void
    {
        $this->selectedLocationId = null;
    }

    public function getSelectedLocationProperty(): ?StorageLocation
    {
        return $this->selectedLocationId ? $this->locations->firstWhere('id', $this->selectedLocationId) : null;
    }

    public function getSelectedOccupantsProperty(): Collection
    {
        if (! $this->selectedLocationId) {
            return collect();
        }

        return $this->occupantsByLocation[$this->selectedLocationId] ?? collect();
    }

    public function render()
    {
        return view('livewire.planta-baixa-grid');
    }
}
