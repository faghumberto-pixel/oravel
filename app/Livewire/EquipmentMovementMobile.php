<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementItem;
use App\Models\EquipmentMovementLocation;
use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\FreightRecord;
use App\Models\MaintenanceOrder;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
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

    public ?string $fleetVehicleId = null;

    public ?string $fleetDriverId = null;

    public ?float $kmFinal = null;

    public bool $loadBankTested = false;

    public string $loadBankNotes = '';

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
                // Sem isso a movimentacao nasce com scheduled_at nulo e
                // LogisticaAgendaWidget::fetchEvents() exige
                // whereNotNull('scheduled_at') -- ela nunca aparecia no mapa
                // de Programacao quando criada por aqui (so' aparecia quando
                // nascia pelo fluxo inverso, agendada primeiro no calendario
                // da Logistica). Agora nasce visivel nos dois casos; a
                // Logistica reagenda pelo calendario se precisar de outro
                // horario.
                'scheduled_at' => now(),
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
        $this->fleetVehicleId = $this->equipmentMovement->fleet_vehicle_id;
        $this->fleetDriverId = $this->equipmentMovement->fleet_driver_id;
        $this->kmFinal = $this->equipmentMovement->km_final !== null
            ? (float) $this->equipmentMovement->km_final
            : null;
        $this->loadBankTested = (bool) $this->equipmentMovement->load_bank_tested;
        $this->loadBankNotes = (string) $this->equipmentMovement->load_bank_notes;
    }

    /**
     * Teste em Banco de Carga (locadoras de evento/gerador) -- so' relevante
     * na mobilizacao (entrega do equipamento), salvo em separado do resto
     * do checklist pra nao exigir reabrir o item.
     */
    public function saveLoadBankTest(): void
    {
        if (! (Tenancy::current()?->hasModuleEnabled('banco_de_carga') ?? true)) {
            return;
        }

        $this->validate([
            'loadBankTested' => 'boolean',
            'loadBankNotes' => 'nullable|string|max:2000',
        ]);

        $this->equipmentMovement->update([
            'load_bank_tested' => $this->loadBankTested,
            'load_bank_notes' => $this->loadBankNotes ?: null,
        ]);
    }

    /**
     * So veiculos DISPONIVEL entram na lista, mais o que ja estiver
     * atribuido a esta movimentacao (senao ele some do <select> assim que
     * vira em_rota por causa da propria atribuicao).
     */
    public function getAvailableVehiclesProperty()
    {
        return FleetVehicle::where(function ($query) {
            $query->where('status', FleetVehicle::STATUS_DISPONIVEL);

            if ($this->equipmentMovement->fleet_vehicle_id) {
                $query->orWhere('id', $this->equipmentMovement->fleet_vehicle_id);
            }
        })->orderBy('placa')->get();
    }

    public function getAvailableDriversProperty()
    {
        return FleetDriver::where('active', true)->orderBy('name')->get();
    }

    /**
     * km_inicial e' o snapshot do odometro do veiculo NO MOMENTO da
     * atribuicao -- so grava na primeira vez (evita sobrescrever se o
     * operador trocar de veiculo no meio do caminho e o km_inicial ja
     * tiver sido usado como referencia).
     */
    public function updatedFleetVehicleId(): void
    {
        $vehicleId = $this->fleetVehicleId ?: null;

        $attributes = ['fleet_vehicle_id' => $vehicleId];

        if ($vehicleId && $this->equipmentMovement->km_inicial === null) {
            $attributes['km_inicial'] = FleetVehicle::find($vehicleId)?->km_atual;
        }

        $this->equipmentMovement->update($attributes);
        $this->markStarted();
    }

    public function updatedFleetDriverId(): void
    {
        $this->equipmentMovement->update(['fleet_driver_id' => $this->fleetDriverId ?: null]);
        $this->markStarted();
    }

    /**
     * Checkpoint manual de localizacao durante o transporte -- captura
     * pelo celular do operador, mesmo padrao de lat/lng das fotos do
     * checklist (navigator.geolocation no blade), so que sem foto.
     */
    public function registrarCheckpoint(string $checkpointType, float $lat, float $lng): void
    {
        Validator::make(
            ['checkpoint_type' => $checkpointType, 'lat' => $lat, 'lng' => $lng],
            [
                'checkpoint_type' => 'required|in:'.implode(',', [
                    EquipmentMovementLocation::CHECKPOINT_SAIDA_PATIO,
                    EquipmentMovementLocation::CHECKPOINT_CHECKPOINT,
                    EquipmentMovementLocation::CHECKPOINT_CHEGADA_DESTINO,
                    EquipmentMovementLocation::CHECKPOINT_SAIDA_CLIENTE,
                    EquipmentMovementLocation::CHECKPOINT_CHEGADA_PATIO,
                ]),
                'lat' => 'required|numeric|between:-90,90',
                'lng' => 'required|numeric|between:-180,180',
            ]
        )->validate();

        $this->equipmentMovement->locations()->create([
            'tenant_id' => $this->equipmentMovement->tenant_id,
            'checkpoint_type' => $checkpointType,
            'latitude' => $lat,
            'longitude' => $lng,
            'captured_at' => now(),
            'captured_by_user_id' => auth()->id(),
        ]);

        $this->markStarted();
    }

    public function getLocationsProperty()
    {
        return $this->equipmentMovement->locations;
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

    /**
     * Veiculo + KM final sao obrigatorios pra finalizar mobilizacao/
     * desmobilizacao -- nao da' pra saber quanto custou o transporte
     * (matriz -> cliente ou vice-versa) sem os dois. Mesma trava de
     * "progress < 100" do checklist, so' que pro veiculo/km.
     */
    public function finalize()
    {
        if ($this->progress < 100) {
            return;
        }

        if (! $this->equipmentMovement->fleet_vehicle_id) {
            $this->addError('vehicleRequired', 'Selecione o veículo usado nesta movimentação antes de finalizar.');

            return;
        }

        if ($this->kmFinal === null) {
            $this->addError('kmRequired', 'Informe o KM final do veículo antes de finalizar.');

            return;
        }

        if ($this->kmFinal <= (float) $this->equipmentMovement->km_inicial) {
            $this->addError('kmRequired', 'O KM final deve ser maior que o KM inicial ('.$this->equipmentMovement->km_inicial.').');

            return;
        }

        $this->equipmentMovement->update([
            'status' => EquipmentMovement::STATUS_CONCLUIDO,
            'completed_at' => now(),
            'km_final' => $this->kmFinal,
        ]);

        $vehicle = $this->equipmentMovement->fleetVehicle;
        $kmPercorrido = $this->equipmentMovement->km_percorrido;

        FreightRecord::create([
            'tenant_id' => $this->equipmentMovement->tenant_id,
            'equipment_movement_id' => $this->equipmentMovement->id,
            'tipo' => FreightRecord::TIPO_PROPRIO,
            'fleet_vehicle_id' => $vehicle?->id,
            'km_percorrido' => $kmPercorrido,
            'valor' => $vehicle?->custo_por_km ? $kmPercorrido * (float) $vehicle->custo_por_km : 0,
            'data' => now(),
        ]);

        $vehicle?->update(['km_atual' => $this->kmFinal]);

        // Checklist de coleta concluido (normalmente feito no cliente) NAO
        // e' a mesma coisa que "chegou de volta no patio de verdade" -- o
        // ativo continua "aguardando triagem" ate um responsavel do patio
        // confirmar a chegada formalmente em App\Filament\Pages\PatioChegadas
        // (EquipmentPatioArrival), que so entao libera pra disponivel. Antes
        // essa distincao nao existia e o ativo virava disponivel na hora.

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
