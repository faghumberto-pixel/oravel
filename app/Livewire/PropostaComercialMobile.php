<?php

namespace App\Livewire;

use App\Models\AssetCategory;
use App\Models\Client;
use App\Models\CrmLead;
use App\Models\PropostaComercial;
use App\Models\PropostaComercialItem;
use App\Models\PropostaComercialTemplate;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Wizard mobile do vendedor de campo -- mesmo padrão estrutural de
 * EquipmentDamageMobile (Gate::authorize no mount() e em cada método de
 * escrita, getters computados, ações via wire:click, persistência
 * incremental). 4 passos: Cliente/Lead → Itens → Termos → Revisão/Enviar.
 */
#[Layout('layouts.checklist-mobile')]
class PropostaComercialMobile extends Component
{
    #[Url(as: 'proposta')]
    public ?string $propostaId = null;

    #[Url(as: 'lead')]
    public ?string $leadId = null;

    public ?PropostaComercial $proposta = null;

    public int $step = 1;

    // Passo 1 — Cliente/Lead
    public ?string $clientId = null;

    // Passo 2 — novo item
    public string $itemType = PropostaComercialItem::TYPE_EQUIPAMENTO;

    public ?string $itemAssetCategoryId = null;

    public string $itemDescription = '';

    public float $itemQuantity = 1;

    public float $itemUnitPrice = 0;

    public ?string $itemUnitPeriod = null;

    public ?string $itemStartDate = null;

    public ?string $itemEndDate = null;

    public ?string $itemTerms = null;

    // Passo 3 — Termos
    public ?string $templateId = null;

    public ?string $terms = null;

    public ?string $validUntil = null;

    public function mount(): void
    {
        Gate::authorize('create', PropostaComercial::class);

        if ($this->propostaId) {
            $this->proposta = PropostaComercial::findOrFail($this->propostaId);
            Gate::authorize('update', $this->proposta);
            $this->clientId = $this->proposta->client_id;
            $this->terms = $this->proposta->terms;
            $this->validUntil = $this->proposta->valid_until?->toDateString();

            return;
        }

        $tenantId = Tenancy::current()?->id;

        $this->proposta = PropostaComercial::create([
            'tenant_id' => $tenantId,
            'seller_user_id' => auth()->id(),
            'crm_lead_id' => $this->leadId,
        ]);

        if ($this->leadId) {
            $lead = CrmLead::find($this->leadId);
            if ($lead?->client_id) {
                $this->clientId = $lead->client_id;
                $this->proposta->update(['client_id' => $lead->client_id]);
            }
        }
    }

    public function getIsLockedProperty(): bool
    {
        return ! in_array($this->proposta?->status, [PropostaComercial::STATUS_RASCUNHO], true);
    }

    public function getClientOptionsProperty()
    {
        return Client::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id');
    }

    public function getAssetCategoryOptionsProperty()
    {
        return AssetCategory::where('tenant_id', Tenancy::current()?->id)->orderBy('name')->pluck('name', 'id');
    }

    public function getTemplateOptionsProperty()
    {
        return PropostaComercialTemplate::where('tenant_id', Tenancy::current()?->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    public function goToStep(int $step): void
    {
        $this->step = $step;
    }

    public function saveClient(): void
    {
        if ($this->isLocked) {
            return;
        }

        $this->validate(['clientId' => 'required|exists:clients,id']);

        Gate::authorize('update', $this->proposta);

        $this->proposta->update(['client_id' => $this->clientId]);
        $this->step = 2;
    }

    public function addItem(): void
    {
        if ($this->isLocked) {
            return;
        }

        $rules = [
            'itemType' => 'required|in:'.implode(',', [PropostaComercialItem::TYPE_EQUIPAMENTO, PropostaComercialItem::TYPE_SERVICO]),
            'itemDescription' => 'required|string|max:255',
            'itemQuantity' => 'required|numeric|min:0.01',
            'itemUnitPrice' => 'required|numeric|min:0',
            'itemUnitPeriod' => 'nullable|string',
            'itemStartDate' => 'nullable|date',
            'itemEndDate' => 'nullable|date|after_or_equal:itemStartDate',
            'itemTerms' => 'nullable|string|max:2000',
        ];

        if ($this->itemType === PropostaComercialItem::TYPE_EQUIPAMENTO) {
            $rules['itemAssetCategoryId'] = 'required|exists:asset_categories,id';
        }

        $this->validate($rules);

        Gate::authorize('update', $this->proposta);

        $this->proposta->items()->create([
            'tenant_id' => $this->proposta->tenant_id,
            'type' => $this->itemType,
            'asset_category_id' => $this->itemType === PropostaComercialItem::TYPE_EQUIPAMENTO ? $this->itemAssetCategoryId : null,
            'description' => $this->itemDescription,
            'quantity' => $this->itemQuantity,
            'unit_price' => $this->itemUnitPrice,
            'unit_period' => $this->itemUnitPeriod,
            'start_date' => $this->itemStartDate,
            'end_date' => $this->itemEndDate,
            'item_terms' => $this->itemTerms,
        ]);

        $this->reset(['itemAssetCategoryId', 'itemDescription', 'itemQuantity', 'itemUnitPrice', 'itemUnitPeriod', 'itemStartDate', 'itemEndDate', 'itemTerms']);
        $this->itemQuantity = 1;
        $this->itemUnitPrice = 0;
        $this->itemType = PropostaComercialItem::TYPE_EQUIPAMENTO;
        $this->proposta->refresh();
    }

    public function removeItem(string $itemId): void
    {
        if ($this->isLocked) {
            return;
        }

        Gate::authorize('update', $this->proposta);

        $this->proposta->items()->whereKey($itemId)->delete();
        $this->proposta->refresh();
    }

    public function applyTemplate(): void
    {
        if ($this->isLocked || ! $this->templateId) {
            return;
        }

        $template = PropostaComercialTemplate::find($this->templateId);
        $this->terms = $template?->default_terms;

        if ($template?->default_valid_days && ! $this->validUntil) {
            $this->validUntil = now()->addDays($template->default_valid_days)->toDateString();
        }
    }

    public function saveTerms(): void
    {
        if ($this->isLocked) {
            return;
        }

        $this->validate([
            'terms' => 'nullable|string',
            'validUntil' => 'nullable|date',
        ]);

        Gate::authorize('update', $this->proposta);

        $this->proposta->update(['terms' => $this->terms, 'valid_until' => $this->validUntil]);
        $this->step = 4;
    }

    public function enviar(): void
    {
        Gate::authorize('update', $this->proposta);

        try {
            $this->proposta->enviarParaComercial();
        } catch (\RuntimeException $e) {
            $this->addError('enviar', $e->getMessage());

            return;
        }

        $this->proposta->refresh();
    }

    public function back()
    {
        return redirect()->route('proposta-comercial.mobile.create');
    }

    public function render()
    {
        return view('livewire.proposta-comercial-mobile');
    }
}
