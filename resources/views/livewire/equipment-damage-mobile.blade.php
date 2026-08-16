@php
    $severityLabels = [
        'leve' => 'Leve',
        'moderada' => 'Moderada',
        'grave' => 'Grave / Perda Total',
    ];
    $damageTypeLabels = \App\Models\EquipmentDamage::damageTypeLabels();
    $causeLabels = \App\Models\EquipmentDamage::causeLabels();
@endphp

<div class="mx-auto flex min-h-screen max-w-md flex-col md:h-full md:min-h-0 md:overflow-y-auto">
    {{-- Header --}}
    <header class="flex items-center justify-between px-5 pb-2 pt-6">
        <h1 class="text-xs font-bold tracking-widest text-zinc-400">REGISTRO DE AVARIA</h1>
        <div class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-red-500/15 text-red-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.99 4.5h.008v.008h-.008v-.008z" />
                </svg>
            </span>
            <span class="text-xs font-bold tracking-wide text-zinc-300">SISTEMA {{ strtoupper(config('app.name', 'ORAVEL')) }}</span>
        </div>
    </header>

    {{-- Identificação --}}
    <section class="px-5 pb-4">
        <h2 class="text-xl font-extrabold leading-tight text-white">
            OS Nº. {{ $equipmentMovement->maintenanceOrder->os_number }}
        </h2>
        <p class="mt-1 text-sm font-medium text-zinc-400">
            ATIVO: {{ strtoupper($equipmentMovement->asset?->name ?? '—') }}
        </p>
        @if($this->originItem)
            <p class="mt-1 text-[11px] font-semibold text-zinc-500">
                Item de origem: {{ $this->originItem->label }}
            </p>
        @endif
    </section>

    <main class="flex-1 space-y-3 overflow-y-auto px-5 pb-4">
        {{-- Detalhes da avaria --}}
        <div class="rounded-2xl bg-zinc-900 p-4">
            <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Detalhes da Avaria</h3>

            <div class="mt-3 space-y-3">
                <div class="flex gap-2">
                    @foreach($severityLabels as $value => $label)
                        <button type="button"
                                @if(!$this->isLocked) wire:click="$set('severity', '{{ $value }}')" @endif
                                @disabled($this->isLocked)
                                class="min-h-[2.5rem] flex-1 rounded-xl border text-[11px] font-bold {{ $severity === $value ? 'border-red-500 bg-red-500/15 text-red-400' : 'border-zinc-700 text-zinc-400' }}">
                            {{ strtoupper($label) }}
                        </button>
                    @endforeach
                </div>
                @error('severity') <p class="text-[11px] text-red-400">{{ $message }}</p> @enderror

                <select wire:model="damageType" @disabled($this->isLocked)
                        class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 disabled:opacity-60">
                    <option value="">Tipo de dano...</option>
                    @foreach($damageTypeLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('damageType') <p class="text-[11px] text-red-400">{{ $message }}</p> @enderror

                <select wire:model="cause" @disabled($this->isLocked)
                        class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 disabled:opacity-60">
                    <option value="">Causa (se souber)...</option>
                    @foreach($causeLabels as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('cause') <p class="text-[11px] text-red-400">{{ $message }}</p> @enderror

                <textarea wire:model="description" rows="4" placeholder="Descreva o dano encontrado..."
                          @disabled($this->isLocked)
                          class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-red-500 disabled:opacity-60"></textarea>
                @error('description') <p class="text-[11px] text-red-400">{{ $message }}</p> @enderror

                <label class="flex items-center justify-between rounded-xl bg-zinc-800 px-3 py-3 text-xs font-semibold text-zinc-300">
                    Exige substituição do equipamento?
                    <input type="checkbox" wire:model="requiresReplacement" @disabled($this->isLocked) class="h-5 w-5 rounded accent-red-500">
                </label>

                @if(!$this->isLocked)
                    <button type="button" wire:click="saveDamageDetails"
                            class="min-h-[2.75rem] w-full rounded-xl bg-red-500 text-xs font-bold text-zinc-950">
                        {{ $damage ? 'ATUALIZAR DETALHES' : 'REGISTRAR AVARIA' }}
                    </button>
                @endif
            </div>
        </div>

        @if($damage)
            {{-- Fotos --}}
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Fotos do Dano</h3>

                @if($damage->getMedia('photos')->count())
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach($damage->getMedia('photos') as $media)
                            <div class="relative h-16 w-16">
                                <img src="{{ $media->getUrl('thumb') }}" class="h-16 w-16 rounded-lg object-cover">
                                @if(!$this->isLocked)
                                    <button type="button" wire:click="removePhoto({{ $media->id }})"
                                            class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
                                        &times;
                                    </button>
                                @endif
                                @if($media->getCustomProperty('latitude'))
                                    <span class="absolute bottom-0.5 right-0.5 rounded bg-black/60 px-1 text-[8px] text-emerald-300">GPS</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                @if(!$this->isLocked)
                    <label class="mt-3 flex min-h-[2.75rem] cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-zinc-700 text-xs font-semibold text-zinc-400">
                        <input
                            type="file"
                            accept="image/*"
                            capture="environment"
                            wire:model="newPhoto"
                            class="hidden"
                            x-on:change="
                                if (navigator.geolocation) {
                                    navigator.geolocation.getCurrentPosition(
                                        (pos) => { $wire.set('newPhotoLat', pos.coords.latitude); $wire.set('newPhotoLng', pos.coords.longitude); },
                                        () => {},
                                        { timeout: 5000, maximumAge: 60000 }
                                    );
                                }
                            "
                        >
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 8a2 2 0 012-2h1l1.2-1.6A2 2 0 019.8 3h4.4a2 2 0 011.6.8L17 5h1a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V8z" />
                            <circle cx="12" cy="13" r="3.5" />
                        </svg>
                        @if($newPhoto)
                            Foto selecionada
                        @else
                            Adicionar foto do dano
                        @endif
                    </label>
                    <div wire:loading wire:target="newPhoto" class="mt-1 text-[11px] text-zinc-500">Enviando foto...</div>

                    @if($newPhoto)
                        <button type="button" wire:click="savePhoto"
                                class="mt-2 min-h-[2.5rem] w-full rounded-xl bg-red-500 text-xs font-bold text-zinc-950">
                            SALVAR FOTO
                        </button>
                    @endif
                @endif
            </div>

            {{-- Assinatura do cliente --}}
            @if(!$this->isLocked)
                <div class="rounded-2xl bg-zinc-900 p-4">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Ciência do Cliente</h3>

                    @if(!$this->canSign)
                        <p class="mt-2 text-[11px] text-zinc-500">
                            Anexe pelo menos uma foto do dano antes de coletar a assinatura do cliente.
                        </p>
                    @else
                        <p class="mt-2 text-[11px] text-zinc-500">
                            Peça para o cliente assinar abaixo confirmando ciência do dano descrito.
                        </p>
                        <div
                            class="mt-3"
                            x-data="{
                                pad: null,
                                isEmpty: true,
                                initPad() {
                                    this.pad = new SignaturePad(this.$refs.canvas, { penColor: '#fafafa', backgroundColor: '#27272a' });
                                    this.pad.addEventListener('endStroke', () => { this.isEmpty = this.pad.isEmpty(); });
                                    this.resizeCanvas();
                                },
                                resizeCanvas() {
                                    const canvas = this.$refs.canvas;
                                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                                    canvas.width = canvas.offsetWidth * ratio;
                                    canvas.height = canvas.offsetHeight * ratio;
                                    canvas.getContext('2d').scale(ratio, ratio);
                                    this.pad?.clear();
                                },
                                clear() { this.pad.clear(); this.isEmpty = true; },
                                confirmSignature() {
                                    if (this.pad.isEmpty()) return;
                                    $wire.saveSignature(this.pad.toDataURL('image/png'));
                                },
                            }"
                            x-init="initPad()"
                        >
                            <canvas x-ref="canvas" class="h-40 w-full rounded-xl bg-zinc-800"></canvas>
                            @error('signature') <p class="mt-1 text-[11px] text-red-400">{{ $message }}</p> @enderror
                            <div class="mt-2 flex gap-2">
                                <button type="button" x-on:click="clear()"
                                        class="min-h-[2.75rem] flex-1 rounded-xl border border-zinc-700 text-xs font-bold text-zinc-300">
                                    LIMPAR
                                </button>
                                <button type="button" x-on:click="confirmSignature()" x-bind:disabled="isEmpty"
                                        class="min-h-[2.75rem] flex-1 rounded-xl bg-emerald-500 text-xs font-bold text-zinc-950 disabled:bg-zinc-800 disabled:text-zinc-600">
                                    CONFIRMAR ASSINATURA
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <div class="rounded-2xl bg-emerald-500/10 p-4">
                    <p class="text-sm font-bold text-emerald-400">Registro confirmado pelo cliente</p>
                    <p class="mt-1 text-[11px] text-zinc-400">
                        Assinado em {{ $damage->client_acknowledged_at->format('d/m/Y H:i') }}. Aguardando revisão do supervisor de manutenção.
                    </p>
                </div>
            @endif
        @endif
    </main>

    {{-- Rodapé --}}
    <footer class="sticky bottom-0 flex gap-3 border-t border-zinc-800 bg-zinc-950 px-5 py-4">
        <button type="button" wire:click="back"
                class="min-h-[3.25rem] w-full rounded-xl border border-zinc-700 bg-zinc-900 text-sm font-bold tracking-wide text-zinc-300">
            VOLTAR
        </button>
    </footer>
</div>
