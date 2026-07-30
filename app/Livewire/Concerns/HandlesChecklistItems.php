<?php

namespace App\Livewire\Concerns;

use App\Models\MaintenanceOrderChecklist;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Compartilhado entre MaintenanceChecklistMobile e
 * MaintenanceOrderFieldWizard::step2 -- gerencia itens de checklist com 3
 * estados (conforme/nao_conforme/nao_aplicavel), validando que "nao_conforme"
 * exige observacao + foto antes de salvar.
 */
trait HandlesChecklistItems
{
    /**
     * Carrega todos os itens de checklist da O.S., ordenados por criacao.
     */
    public function getItemsProperty()
    {
        return $this->maintenanceOrder->checklists()
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Percentual de conclusao: itens com status 'conforme' ou 'nao_aplicavel'
     * contam como "completos" (o hook em MaintenanceOrderChecklist::booted()
     * ja' faz is_completed refletir isso). Itens sem status ainda (null) contam
     * como incompletos.
     */
    public function getProgressProperty(): int
    {
        $items = $this->items;

        if ($items->isEmpty()) {
            return 0;
        }

        return (int) round($items->where('is_completed', true)->count() / $items->count() * 100);
    }

    /**
     * Cicla entre estados: null → conforme → nao_conforme → nao_aplicavel → null.
     * Usado pelo botao "X" pra limpar um status existente sem passar por todos.
     */
    public function toggleItemStatus(string $itemId): void
    {
        $item = $this->maintenanceOrder->checklists()->whereKey($itemId)->firstOrFail();

        $current = $item->status;
        $next = match ($current) {
            'conforme' => 'nao_conforme',
            'nao_conforme' => 'nao_aplicavel',
            'nao_aplicavel' => null,
            default => 'conforme',
        };

        $item->update(['status' => $next]);

        // Se o item foi zerado, limpa dados associados.
        if ($next === null) {
            $item->notes = null;
            $item->getMedia('photos')->each->delete();
            $item->save();
        }
    }

    /**
     * Define status diretamente (chamado pelos 3 botoes Conforme/N.Conf/N/A).
     * "nao_conforme" nega persist se observation + foto estiverem vazios.
     */
    public function setItemStatus(string $itemId, string $status): void
    {
        $item = MaintenanceOrderChecklist::find($itemId);
        if (! $item) {
            return;
        }

        // Validacao: "nao_conforme" exige observacao + foto.
        if ($status === 'nao_conforme') {
            $hasObservation = ! empty($this->newObservation);
            $hasPhoto = $this->newPhoto !== null || $item->getMedia('photos')->isNotEmpty();

            if (! $hasObservation || ! $hasPhoto) {
                $this->addError('itemStatusError', 'Itens "Não Conforme" exigem observação e foto.');

                return;
            }
        }

        $item->update(['status' => $status]);
    }

    /**
     * Abre o item para edicao (expandir), pre-carregando observacao existente.
     */
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

    /**
     * Fecha o item sem salvar (descarta edicoes).
     */
    public function collapse(): void
    {
        $this->expandedItemId = null;
        $this->newObservation = '';
        $this->newPhoto = null;
    }

    /**
     * Persiste observacao + foto para o item expandido. Nao' altera status
     * aqui -- o status e' setado pelos 3 botoes (setItemStatus).
     */
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

    /**
     * Remove uma midia (foto) do item, via ID da midia na colecao.
     */
    public function removeMedia(int $mediaId): void
    {
        Media::query()
            ->where('model_type', MaintenanceOrderChecklist::class)
            ->whereIn('model_id', $this->maintenanceOrder->checklists()->pluck('id'))
            ->findOrFail($mediaId)
            ->delete();
    }
}
