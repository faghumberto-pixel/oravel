<?php

namespace App\Filament\Concerns;

use App\Models\Contract;
use App\Models\EquipmentDamage;
use App\Models\MaintenanceOrder;
use Illuminate\Support\Collection;

/**
 * Dados sintetizados do Dossie Rapido do Ativo -- compartilhado entre a
 * versao desktop (App\Filament\Pages\AssetDossier) e a mobile
 * (App\Livewire\AssetDossierMobile), que tem visual proprio (Tailwind puro,
 * tema escuro) mas a mesma logica de dados. Exige `public ?Asset $asset`
 * na classe que usa a trait.
 */
trait HasAssetDossierData
{
    public function getCurrentContractProperty(): ?Contract
    {
        return $this->asset?->contracts()
            ->where('status', 'Ativo')
            ->latest('start_date')
            ->first();
    }

    /** @return Collection<int, MaintenanceOrder> */
    public function getOpenOrdersProperty(): Collection
    {
        if (! $this->asset) {
            return collect();
        }

        return $this->asset->maintenanceOrders()
            ->with(['technician', 'reportedProblem'])
            ->whereNotIn('status', ['Concluída', 'Cancelada', 'Cancelado'])
            ->latest('created_at')
            ->get();
    }

    /** @return Collection<int, EquipmentDamage> */
    public function getRecentDamagesProperty(): Collection
    {
        if (! $this->asset) {
            return collect();
        }

        return $this->asset->damages()
            ->latest('created_at')
            ->limit(5)
            ->get();
    }
}
