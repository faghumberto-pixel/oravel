@php
    $itemTypeLabels = \App\Models\PropostaComercialItem::typeLabels();
    $unitPeriodLabels = \App\Models\PropostaComercialItem::unitPeriodLabels();
@endphp

<div class="mx-auto flex min-h-screen max-w-md flex-col md:h-full md:min-h-0 md:overflow-y-auto">
    {{-- Header --}}
    <header class="flex items-center justify-between px-5 pb-2 pt-6">
        <h1 class="text-xs font-bold tracking-widest text-zinc-400">NOVA PROPOSTA COMERCIAL</h1>
        <div class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-orange-500/15 text-orange-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
            </span>
            <span class="text-xs font-bold tracking-wide text-zinc-300">SISTEMA {{ strtoupper(config('app.name', 'ORAVEL')) }}</span>
        </div>
    </header>

    {{-- Passos --}}
    <div class="flex gap-1.5 px-5 pb-4">
        @foreach ([1 => 'Cliente', 2 => 'Itens', 3 => 'Termos', 4 => 'Revisão'] as $n => $label)
            <div class="flex-1 rounded-full {{ $step >= $n ? 'bg-orange-500' : 'bg-zinc-800' }} h-1.5"></div>
        @endforeach
    </div>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-4">

        {{-- PASSO 1 — CLIENTE --}}
        @if ($step === 1)
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Cliente</h3>
                <p class="mt-1 text-[11px] text-zinc-500">Selecione o cliente desta proposta.</p>

                <select wire:model="clientId" class="mt-3 w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100">
                    <option value="">Selecione...</option>
                    @foreach ($this->clientOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                @error('clientId') <p class="mt-1 text-[11px] text-red-400">{{ $message }}</p> @enderror

                <button type="button" wire:click="saveClient"
                        class="mt-4 min-h-[2.75rem] w-full rounded-xl bg-orange-500 text-xs font-bold text-zinc-950">
                    PRÓXIMO — ITENS
                </button>
            </div>
        @endif

        {{-- PASSO 2 — ITENS --}}
        @if ($step === 2)
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Itens da Proposta</h3>

                @if ($proposta->items->isNotEmpty())
                    <div class="mt-3 space-y-2">
                        @foreach ($proposta->items as $item)
                            <div class="flex items-center justify-between rounded-xl bg-zinc-800 px-3 py-2.5">
                                <div>
                                    <p class="text-xs font-bold text-zinc-100">{{ $item->description }}</p>
                                    <p class="text-[10px] text-zinc-500">{{ $itemTypeLabels[$item->type] }} · {{ $item->quantity }}x R$ {{ number_format($item->unit_price, 2, ',', '.') }}</p>
                                </div>
                                <button type="button" wire:click="removeItem('{{ $item->id }}')" class="text-red-400 text-xs font-bold">&times;</button>
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-3 flex items-center justify-between border-t border-zinc-800 pt-3">
                        <span class="text-[11px] font-bold uppercase text-zinc-400">Total</span>
                        <span class="text-sm font-bold text-orange-400">R$ {{ number_format($proposta->total_value, 2, ',', '.') }}</span>
                    </div>
                @endif

                <div class="mt-4 space-y-3 border-t border-zinc-800 pt-4">
                    <div class="flex gap-2">
                        @foreach ($itemTypeLabels as $value => $label)
                            <button type="button" wire:click="$set('itemType', '{{ $value }}')"
                                    class="min-h-[2.5rem] flex-1 rounded-xl border text-[11px] font-bold {{ $itemType === $value ? 'border-orange-500 bg-orange-500/15 text-orange-400' : 'border-zinc-700 text-zinc-400' }}">
                                {{ strtoupper($label) }}
                            </button>
                        @endforeach
                    </div>

                    @if ($itemType === \App\Models\PropostaComercialItem::TYPE_EQUIPAMENTO)
                        <select wire:model="itemAssetCategoryId" class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100">
                            <option value="">Categoria do equipamento...</option>
                            @foreach ($this->assetCategoryOptions as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                        @error('itemAssetCategoryId') <p class="text-[11px] text-red-400">{{ $message }}</p> @enderror
                    @endif

                    <input type="text" wire:model="itemDescription"
                           placeholder="{{ $itemType === \App\Models\PropostaComercialItem::TYPE_SERVICO ? 'Ex: Técnico 24h dedicado, Motorista...' : 'Ex: Gerador 180 kVA c/ cabo' }}"
                           class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 placeholder:text-zinc-500">
                    @error('itemDescription') <p class="text-[11px] text-red-400">{{ $message }}</p> @enderror

                    <div class="grid grid-cols-2 gap-2">
                        <input type="number" step="0.01" wire:model="itemQuantity" placeholder="Quantidade"
                               class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100">
                        <input type="number" step="0.01" wire:model="itemUnitPrice" placeholder="Valor unitário (R$)"
                               class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100">
                    </div>

                    <select wire:model="itemUnitPeriod" class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100">
                        <option value="">Período (se aplicável)...</option>
                        @foreach ($unitPeriodLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>

                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[10px] text-zinc-500">Início</label>
                            <input type="date" wire:model="itemStartDate" class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100">
                        </div>
                        <div>
                            <label class="text-[10px] text-zinc-500">Fim</label>
                            <input type="date" wire:model="itemEndDate" class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100">
                        </div>
                    </div>
                    @error('itemEndDate') <p class="text-[11px] text-red-400">{{ $message }}</p> @enderror

                    <textarea wire:model="itemTerms" rows="2" placeholder="Exigência específica deste item (ex: exige operador certificado NR)..."
                              class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 placeholder:text-zinc-500"></textarea>

                    <button type="button" wire:click="addItem"
                            class="min-h-[2.75rem] w-full rounded-xl bg-zinc-800 border border-orange-500/40 text-xs font-bold text-orange-400">
                        + ADICIONAR ITEM
                    </button>
                </div>

                <button type="button" wire:click="goToStep(3)"
                        class="mt-4 min-h-[2.75rem] w-full rounded-xl bg-orange-500 text-xs font-bold text-zinc-950">
                    PRÓXIMO — TERMOS
                </button>
            </div>
        @endif

        {{-- PASSO 3 — TERMOS --}}
        @if ($step === 3)
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Termos da Proposta</h3>

                @if ($this->templateOptions->isNotEmpty())
                    <select wire:model.live="templateId" wire:change="applyTemplate"
                            class="mt-3 w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100">
                        <option value="">Usar template...</option>
                        @foreach ($this->templateOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                @endif

                <textarea wire:model="terms" rows="6" placeholder="Termos e condições gerais desta proposta..."
                          class="mt-3 w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 placeholder:text-zinc-500"></textarea>

                <div class="mt-3">
                    <label class="text-[10px] text-zinc-500">Válida até</label>
                    <input type="date" wire:model="validUntil" class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100">
                </div>

                <button type="button" wire:click="saveTerms"
                        class="mt-4 min-h-[2.75rem] w-full rounded-xl bg-orange-500 text-xs font-bold text-zinc-950">
                    PRÓXIMO — REVISÃO
                </button>
            </div>
        @endif

        {{-- PASSO 4 — REVISÃO E ENVIO --}}
        @if ($step === 4)
            @if ($proposta->status === \App\Models\PropostaComercial::STATUS_RASCUNHO)
                <div class="rounded-2xl bg-zinc-900 p-4">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Revisão Final</h3>

                    <div class="mt-3 space-y-2">
                        @foreach ($proposta->items as $item)
                            <div class="flex items-center justify-between rounded-xl bg-zinc-800 px-3 py-2.5">
                                <div>
                                    <p class="text-xs font-bold text-zinc-100">{{ $item->description }}</p>
                                    <p class="text-[10px] text-zinc-500">{{ $itemTypeLabels[$item->type] }} · {{ $item->quantity }}x R$ {{ number_format($item->unit_price, 2, ',', '.') }}</p>
                                </div>
                                <span class="text-xs font-bold text-orange-400">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-3 flex items-center justify-between border-t border-zinc-800 pt-3">
                        <span class="text-[11px] font-bold uppercase text-zinc-400">Total</span>
                        <span class="text-sm font-bold text-orange-400">R$ {{ number_format($proposta->total_value, 2, ',', '.') }}</span>
                    </div>

                    @error('enviar') <p class="mt-3 text-[11px] text-red-400">{{ $message }}</p> @enderror

                    <button type="button" wire:click="enviar"
                            class="mt-4 min-h-[3rem] w-full rounded-xl bg-orange-500 text-sm font-bold text-zinc-950">
                        ENVIAR PARA O COMERCIAL
                    </button>
                </div>
            @else
                <div class="rounded-2xl bg-emerald-500/10 p-4">
                    <p class="text-sm font-bold text-emerald-400">Proposta enviada ao Comercial</p>
                    <p class="mt-1 text-[11px] text-zinc-400">
                        Aguardando revisão. Total: R$ {{ number_format($proposta->total_value, 2, ',', '.') }}
                    </p>
                </div>
            @endif
        @endif
    </main>

    {{-- Rodapé --}}
    <footer class="sticky bottom-0 flex gap-3 border-t border-zinc-800 bg-zinc-950 px-5 py-4">
        @if ($step > 1 && $proposta->status === \App\Models\PropostaComercial::STATUS_RASCUNHO)
            <button type="button" wire:click="goToStep({{ $step - 1 }})"
                    class="min-h-[3.25rem] w-full rounded-xl border border-zinc-700 bg-zinc-900 text-sm font-bold tracking-wide text-zinc-300">
                VOLTAR
            </button>
        @else
            <button type="button" wire:click="back"
                    class="min-h-[3.25rem] w-full rounded-xl border border-zinc-700 bg-zinc-900 text-sm font-bold tracking-wide text-zinc-300">
                SAIR
            </button>
        @endif
    </footer>
</div>
