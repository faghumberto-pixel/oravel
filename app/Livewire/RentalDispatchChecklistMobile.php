<?php

namespace App\Livewire;

use App\Models\EquipmentMovement;
use App\Models\EquipmentMovementItem;
use App\Models\SolicitacaoLocacao;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Checklist de saida do patio pra despacho de locacao (nao de OS de
 * manutencao -- ver EquipmentMovementMobile pra essa outra ponta). Reaproveita
 * o mesmo motor de EquipmentMovement/EquipmentMovementItem, mas finalize()
 * NAO libera direto: vai pra STATUS_AGUARDANDO_APROVACAO e fica travado
 * (QR invalido) ate o gestor do patio aprovar em PatioAprovacoes.
 */
#[Layout('layouts.checklist-mobile')]
class RentalDispatchChecklistMobile extends Component
{
    use WithFileUploads;

    public SolicitacaoLocacao $solicitacaoLocacao;

    public EquipmentMovement $equipmentMovement;

    public ?string $expandedItemId = null;

    public string $newObservation = '';

    public $newPhoto = null;

    public ?float $newPhotoLat = null;

    public ?float $newPhotoLng = null;

    public function mount(SolicitacaoLocacao $solicitacaoLocacao): void
    {
        Gate::authorize('view', $solicitacaoLocacao);

        abort_unless((bool) $solicitacaoLocacao->asset_id, 404, 'Selecione um equipamento específico na solicitação antes de iniciar o checklist de saída.');

        $existing = EquipmentMovement::where('solicitacao_locacao_id', $solicitacaoLocacao->id)
            ->where('type', EquipmentMovement::TYPE_MOBILIZACAO)
            ->latest()
            ->first();

        if ($existing) {
            Gate::authorize('view', $existing);
            $this->equipmentMovement = $existing;
        } else {
            Gate::authorize('create', EquipmentMovement::class);
            $this->equipmentMovement = EquipmentMovement::create([
                'solicitacao_locacao_id' => $solicitacaoLocacao->id,
                'asset_id' => $solicitacaoLocacao->asset_id,
                'type' => EquipmentMovement::TYPE_MOBILIZACAO,
                'status' => EquipmentMovement::STATUS_AGUARDANDO_VISTORIA,
                // scheduled_at=now() pra nascer visivel no mapa de
                // Programacao da Logistica (LogisticaAgendaWidget exige
                // scheduled_at preenchido) -- reagenda pelo calendario se
                // precisar de outro horario.
                'scheduled_at' => now(),
            ]);
        }

        $this->solicitacaoLocacao = $solicitacaoLocacao;
    }

    public function getItemsProperty()
    {
        return $this->equipmentMovement->items()->orderBy('sort_order')->get();
    }

    public function getProgressProperty(): int
    {
        return $this->equipmentMovement->progressPercent();
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

    /**
     * Diferente do EquipmentMovementMobile: NAO conclui direto. Fica
     * "aguardando_aprovacao" ate o gestor do patio dar o OK tecnico.
     */
    public function finalize(): void
    {
        if ($this->progress < 100) {
            return;
        }

        $this->equipmentMovement->update([
            'status' => EquipmentMovement::STATUS_AGUARDANDO_APROVACAO,
            'completed_at' => now(),
        ]);
    }

    public function back()
    {
        return redirect()->route('filament.admin.resources.solicitacoes-locacaos.edit', ['record' => $this->solicitacaoLocacao]);
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
        return view('livewire.rental-dispatch-checklist-mobile');
    }
}
