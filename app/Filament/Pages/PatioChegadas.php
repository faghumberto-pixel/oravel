<?php

namespace App\Filament\Pages;

use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\EquipmentPatioArrival;
use App\Support\Tenancy;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Evento formal de "chegada no patio" pra toda desmobilizacao -- ate aqui
 * o checklist de coleta (feito no cliente) ja liberava o ativo como
 * disponivel na hora (EquipmentMovementMobile::finalize()), confundindo
 * "saiu do cliente" com "chegou no patio de verdade". Essa tela exige
 * confirmacao ativa de um responsavel do patio, virando historico
 * permanente por ativo (EquipmentPatioArrival).
 */
class PatioChegadas extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Chegadas no Pátio';

    protected static ?string $title = 'Chegadas no Pátio';

    protected static ?string $navigationGroup = 'Logística';

    protected static string $view = 'filament.pages.patio-chegadas';

    public ?string $confirmingId = null;

    public string $conditionNotes = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('viewAny', EquipmentMovement::class);
    }

    public function getPendingProperty(): Collection
    {
        $tenant = Tenancy::current();
        if (! $tenant) {
            return collect();
        }

        return EquipmentMovement::where('tenant_id', $tenant->id)
            ->where('type', EquipmentMovement::TYPE_DESMOBILIZACAO)
            ->where('status', EquipmentMovement::STATUS_CONCLUIDO)
            ->whereDoesntHave('patioArrival')
            ->with(['asset', 'maintenanceOrder.client'])
            ->latest('completed_at')
            ->get();
    }

    public function confirm(?string $id): void
    {
        $this->confirmingId = $this->confirmingId === $id ? null : $id;
        $this->conditionNotes = '';
    }

    public function registerArrival(string $id): void
    {
        $movement = EquipmentMovement::where('type', EquipmentMovement::TYPE_DESMOBILIZACAO)
            ->where('status', EquipmentMovement::STATUS_CONCLUIDO)
            ->whereDoesntHave('patioArrival')
            ->findOrFail($id);

        EquipmentPatioArrival::create([
            'tenant_id' => $movement->tenant_id,
            'equipment_movement_id' => $movement->id,
            'arrived_at' => now(),
            'confirmed_by_user_id' => Auth::id(),
            'initial_condition_notes' => $this->conditionNotes ?: null,
        ]);

        $movement->asset?->update(['status' => Asset::STATUS_DISPONIVEL]);

        Notification::make()->title('Chegada no pátio confirmada.')->success()->send();

        $this->confirm(null);
    }
}
