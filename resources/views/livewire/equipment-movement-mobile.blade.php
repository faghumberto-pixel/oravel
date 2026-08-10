@php
    $typeLabels = [
        'mobilizacao' => 'MOBILIZAÇÃO',
        'desmobilizacao' => 'DESMOBILIZAÇÃO',
    ];
    $statusLabels = [
        'aguardando_vistoria' => 'Aguardando Vistoria',
        'em_andamento' => 'Em Andamento',
        'concluido' => 'Concluído',
    ];
    $coverMedia = $equipmentMovement->getFirstMedia('vistoria_geral');
@endphp

<div class="mx-auto flex min-h-screen max-w-md flex-col">
    {{-- Header --}}
    <header class="flex items-center justify-between px-5 pb-2 pt-6">
        <h1 class="text-xs font-bold tracking-widest text-zinc-400">{{ $typeLabels[$equipmentMovement->type] ?? 'MOVIMENTAÇÃO' }}</h1>
        <div class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 2l7 3v6c0 5-3.2 8.4-7 10-3.8-1.6-7-5-7-10V5l7-3z" />
                </svg>
            </span>
            <span class="text-xs font-bold tracking-wide text-zinc-300">SISTEMA {{ strtoupper(config('app.name', 'ORAVEL')) }}</span>
        </div>
    </header>

    {{-- Bloco de identificação --}}
    <section class="px-5 pb-4">
        <h2 class="text-xl font-extrabold leading-tight text-white">
            ORDEM DE SERVIÇO Nº. {{ $maintenanceOrder->os_number }}
        </h2>
        <p class="mt-1 text-sm font-medium text-zinc-400">
            VEÍCULO: {{ strtoupper($maintenanceOrder->asset?->name ?? '—') }}
            @if($maintenanceOrder->asset?->asset_tag)
                , TAG: {{ strtoupper($maintenanceOrder->asset->asset_tag) }}
            @endif
        </p>

        <div class="mt-3 flex items-center gap-4 text-[11px] font-semibold text-zinc-400">
            <span class="rounded-full bg-zinc-800 px-2 py-1 text-zinc-300">
                {{ $statusLabels[$equipmentMovement->status] ?? $equipmentMovement->status }}
            </span>
            <span class="ml-auto flex items-center gap-1 text-emerald-400">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5">
                    <circle cx="12" cy="12" r="9" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.5l2 2 4-4.5" />
                </svg>
                {{ $this->progress }}%
            </span>
        </div>

        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-zinc-800">
            <div class="h-2 rounded-full bg-emerald-500 transition-all duration-300" style="width: {{ $this->progress }}%"></div>
        </div>
    </section>

    {{-- Veiculo & Motorista --}}
    <section class="px-5 pb-4">
        <div class="rounded-2xl bg-zinc-900 p-4">
            <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Veículo &amp; Motorista</h3>

            <div class="mt-3 space-y-3">
                <select wire:model.live="fleetVehicleId"
                        class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 focus:ring-2 focus:ring-emerald-500">
                    <option value="">Selecione o veículo...</option>
                    @foreach($this->availableVehicles as $vehicle)
                        <option value="{{ $vehicle->id }}">{{ $vehicle->placa }} — {{ $vehicle->modelo }}</option>
                    @endforeach
                </select>

                @if($this->availableVehicles->isEmpty())
                    <p class="text-[11px] text-amber-400">Nenhum veículo próprio disponível agora — considere acionar frete terceirizado.</p>
                @endif

                @if($fleetVehicleId)
                    <div>
                        <label class="mb-1 block text-[11px] font-semibold text-zinc-400">
                            KM Final do Veículo
                            @if($equipmentMovement->km_inicial !== null)
                                <span class="text-zinc-500">(inicial: {{ $equipmentMovement->km_inicial }})</span>
                            @endif
                        </label>
                        <input type="number" step="0.1" wire:model="kmFinal" placeholder="Ex: 12345.6"
                               class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-emerald-500">
                    </div>
                @endif

                <select wire:model.live="fleetDriverId"
                        class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 focus:ring-2 focus:ring-emerald-500">
                    <option value="">Selecione o motorista...</option>
                    @foreach($this->availableDrivers as $driver)
                        <option value="{{ $driver->id }}">{{ $driver->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    {{-- Rastreamento: checkpoints de localizacao --}}
    <section class="px-5 pb-4">
        <div class="rounded-2xl bg-zinc-900 p-4"
             x-data="{
                capturando: false,
                registrar(tipo) {
                    this.capturando = true;
                    if (! navigator.geolocation) { this.capturando = false; return; }
                    navigator.geolocation.getCurrentPosition(
                        (pos) => {
                            $wire.call('registrarCheckpoint', tipo, pos.coords.latitude, pos.coords.longitude)
                                .then(() => { this.capturando = false; });
                        },
                        () => { this.capturando = false; },
                        { timeout: 8000, maximumAge: 60000 }
                    );
                }
             }">
            <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Rastreamento do Transporte</h3>

            <div class="mt-3 grid grid-cols-1 gap-2">
                @if($equipmentMovement->type === \App\Models\EquipmentMovement::TYPE_MOBILIZACAO)
                    <button type="button" x-on:click="registrar('saida_patio')" :disabled="capturando"
                            class="min-h-[2.75rem] rounded-xl border border-zinc-700 text-xs font-bold text-zinc-300 disabled:opacity-50">
                        REGISTRAR SAÍDA DO PÁTIO
                    </button>
                    <button type="button" x-on:click="registrar('checkpoint')" :disabled="capturando"
                            class="min-h-[2.75rem] rounded-xl border border-zinc-700 text-xs font-bold text-zinc-300 disabled:opacity-50">
                        REGISTRAR CHECKPOINT
                    </button>
                    <button type="button" x-on:click="registrar('chegada_destino')" :disabled="capturando"
                            class="min-h-[2.75rem] rounded-xl border border-zinc-700 text-xs font-bold text-zinc-300 disabled:opacity-50">
                        REGISTRAR CHEGADA NO DESTINO
                    </button>
                @else
                    <button type="button" x-on:click="registrar('saida_cliente')" :disabled="capturando"
                            class="min-h-[2.75rem] rounded-xl border border-zinc-700 text-xs font-bold text-zinc-300 disabled:opacity-50">
                        REGISTRAR SAÍDA DO CLIENTE
                    </button>
                    <button type="button" x-on:click="registrar('checkpoint')" :disabled="capturando"
                            class="min-h-[2.75rem] rounded-xl border border-zinc-700 text-xs font-bold text-zinc-300 disabled:opacity-50">
                        REGISTRAR CHECKPOINT
                    </button>
                    <button type="button" x-on:click="registrar('chegada_patio')" :disabled="capturando"
                            class="min-h-[2.75rem] rounded-xl border border-zinc-700 text-xs font-bold text-zinc-300 disabled:opacity-50">
                        REGISTRAR CHEGADA NO PÁTIO
                    </button>
                @endif
            </div>

            @if($this->locations->isNotEmpty())
                <ul class="mt-3 space-y-1 text-[11px] text-zinc-500">
                    @foreach($this->locations as $location)
                        <li>{{ $location->captured_at->format('d/m H:i') }} — {{ str($location->checkpoint_type)->replace('_', ' ')->title() }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </section>

    {{-- Banco de Carga (locadoras de evento/gerador) -- so' na mobilizacao --}}
    @if($equipmentMovement->type === \App\Models\EquipmentMovement::TYPE_MOBILIZACAO && (\App\Support\Tenancy::current()?->hasModuleEnabled('banco_de_carga') ?? true))
        <section class="px-5 pb-4">
            <div class="rounded-2xl bg-zinc-900 p-4">
                <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Teste em Banco de Carga</h3>

                <label class="mt-3 flex items-center gap-2 rounded-xl bg-zinc-800 p-3 text-sm text-zinc-100">
                    <input type="checkbox" wire:model.live="loadBankTested" wire:change="saveLoadBankTest"
                           class="h-5 w-5 rounded border-zinc-600 bg-zinc-700 text-emerald-500 focus:ring-emerald-500">
                    <span>Equipamento testado em banco de carga antes da liberação</span>
                </label>

                <textarea wire:model.blur="loadBankNotes" wire:change="saveLoadBankTest" rows="2"
                          placeholder="Observações do teste (kW aplicado, duração, resultado...)"
                          class="mt-2 w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>
        </section>
    @endif

    {{-- Vistoria Geral (foto de capa) --}}
    <section class="px-5 pb-4">
        <div class="rounded-2xl bg-zinc-900 p-4">
            <h3 class="text-xs font-bold uppercase tracking-wide text-zinc-400">Vistoria Geral</h3>

            @if($coverMedia)
                <div class="mt-3 flex items-center gap-3">
                    <img src="{{ $coverMedia->getUrl('thumb') }}" class="h-16 w-16 rounded-lg object-cover">
                    <div class="text-[11px] text-zinc-400">
                        <p>Foto registrada</p>
                        @if($equipmentMovement->vistoria_geral_lat)
                            <p>Localização capturada</p>
                        @endif
                    </div>
                </div>
            @else
                <div
                    x-data
                    class="mt-3"
                >
                    <label class="flex min-h-[2.75rem] cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-zinc-700 text-xs font-semibold text-zinc-400">
                        <input
                            type="file"
                            accept="image/*"
                            capture="environment"
                            wire:model="newCoverPhoto"
                            class="hidden"
                            x-on:change="
                                if (navigator.geolocation) {
                                    navigator.geolocation.getCurrentPosition(
                                        (pos) => { $wire.set('coverPhotoLat', pos.coords.latitude); $wire.set('coverPhotoLng', pos.coords.longitude); },
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
                        @if($newCoverPhoto)
                            Foto selecionada
                        @else
                            Tirar foto geral do equipamento
                        @endif
                    </label>
                    <div wire:loading wire:target="newCoverPhoto" class="mt-1 text-[11px] text-zinc-500">Enviando foto...</div>

                    @if($newCoverPhoto)
                        <button type="button" wire:click="saveVistoriaGeral"
                                class="mt-2 min-h-[2.5rem] w-full rounded-xl bg-emerald-500 text-xs font-bold text-zinc-950">
                            SALVAR FOTO GERAL
                        </button>
                    @endif
                </div>
            @endif
        </div>
    </section>

    {{-- Registrar avaria (nao ligada a nenhum item especifico) --}}
    <section class="px-5 pb-4">
        <a href="{{ route('equipment-movements.damages.create', $equipmentMovement) }}"
           class="flex min-h-[2.75rem] w-full items-center justify-center gap-2 rounded-xl border border-red-500/40 bg-red-500/10 text-xs font-bold text-red-400">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-8.99 4.5h.008v.008h-.008v-.008z" />
            </svg>
            REGISTRAR AVARIA
        </a>
    </section>

    @error('photoRequired')
        <div class="mx-5 mb-3 rounded-xl bg-red-500/15 px-3 py-2 text-xs font-semibold text-red-400">
            {{ $message }}
        </div>
    @enderror

    @error('vehicleRequired')
        <div class="mx-5 mb-3 rounded-xl bg-red-500/15 px-3 py-2 text-xs font-semibold text-red-400">
            {{ $message }}
        </div>
    @enderror

    @error('kmRequired')
        <div class="mx-5 mb-3 rounded-xl bg-red-500/15 px-3 py-2 text-xs font-semibold text-red-400">
            {{ $message }}
        </div>
    @enderror

    {{-- Lista de checklist --}}
    <main class="flex-1 space-y-2 overflow-y-auto px-5 pb-4">
        @forelse($this->items as $index => $item)
            <div class="overflow-hidden rounded-2xl bg-zinc-900">
                <div class="flex w-full items-center gap-3 px-4 py-4">
                    <button type="button" wire:click="expand('{{ $item->id }}')" class="flex flex-1 items-center gap-3 text-left">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             class="h-4 w-4 shrink-0 text-zinc-600 transition-transform {{ $expandedItemId === $item->id ? 'rotate-90' : '' }}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" />
                        </svg>
                        <span class="text-sm font-semibold uppercase leading-snug text-zinc-100">
                            {{ $index + 1 }}. {{ $item->label }}
                            @if($item->requires_photo)
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="ml-1 inline h-3.5 w-3.5 text-zinc-500">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 8a2 2 0 012-2h1l1.2-1.6A2 2 0 019.8 3h4.4a2 2 0 011.6.8L17 5h1a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V8z" />
                                    <circle cx="12" cy="13" r="3.5" />
                                </svg>
                            @endif
                        </span>
                    </button>

                    <button type="button" wire:click="toggleItem('{{ $item->id }}')"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full transition-colors {{ $item->is_checked ? 'bg-emerald-500 text-zinc-950' : 'bg-zinc-800 text-zinc-600' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                </div>

                @if($expandedItemId === $item->id)
                    <div class="space-y-3 border-t border-zinc-800 px-4 py-4">
                        <div>
                            <label class="mb-1 block text-[11px] font-semibold uppercase text-zinc-500">Resultado da Inspeção</label>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach(\App\Models\EquipmentMovementItem::resultLabels() as $value => $resultLabel)
                                    <button type="button" wire:click="setResult('{{ $value }}')"
                                            class="min-h-[2.5rem] rounded-xl border text-xs font-bold transition-colors {{ $newResult === $value
                                                ? ($value === 'nok' ? 'border-red-500 bg-red-500/15 text-red-400' : ($value === 'ok' ? 'border-emerald-500 bg-emerald-500/15 text-emerald-400' : 'border-zinc-500 bg-zinc-500/15 text-zinc-300'))
                                                : 'border-zinc-700 text-zinc-400' }}">
                                        {{ $resultLabel }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        @if($this->isHorimeterItem($item))
                            <div>
                                <label class="mb-1 block text-[11px] font-semibold uppercase text-zinc-500">Horímetro (horas)</label>
                                <input type="number" step="0.1" min="0" wire:model="newHorimeterValue" placeholder="Ex: 1234.5"
                                       class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-emerald-500">
                                @error('newHorimeterValue')
                                    <p class="mt-1 text-[11px] font-semibold text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        @elseif($item->value !== null)
                            <p class="text-[11px] text-zinc-500">Valor registrado: <span class="text-zinc-300">{{ $item->value }}</span></p>
                        @endif

                        <textarea wire:model="newObservation" rows="3" placeholder="Observação sobre este item..."
                                  class="w-full rounded-xl border-0 bg-zinc-800 p-3 text-sm text-zinc-100 placeholder:text-zinc-500 focus:ring-2 focus:ring-emerald-500"></textarea>

                        @if($item->getMedia('photos')->count())
                            <div class="flex flex-wrap gap-2">
                                @foreach($item->getMedia('photos') as $media)
                                    <div class="relative h-16 w-16">
                                        <img src="{{ $media->getUrl('thumb') }}" class="h-16 w-16 rounded-lg object-cover">
                                        <button type="button" wire:click="removeMedia({{ $media->id }})"
                                                class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
                                            &times;
                                        </button>
                                        @if($media->getCustomProperty('latitude'))
                                            <span class="absolute bottom-0.5 right-0.5 rounded bg-black/60 px-1 text-[8px] text-emerald-300">GPS</span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <label class="flex min-h-[2.75rem] cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-zinc-700 text-xs font-semibold text-zinc-400">
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
                                Adicionar foto
                            @endif
                        </label>
                        <div wire:loading wire:target="newPhoto" class="text-[11px] text-zinc-500">Enviando foto...</div>

                        <div class="flex gap-2 pt-1">
                            <button type="button" wire:click="collapse"
                                    class="min-h-[2.75rem] flex-1 rounded-xl border border-zinc-700 text-xs font-bold text-zinc-300">
                                CANCELAR
                            </button>
                            <button type="button" wire:click="saveItemDetails"
                                    class="min-h-[2.75rem] flex-1 rounded-xl bg-emerald-500 text-xs font-bold text-zinc-950">
                                SALVAR
                            </button>
                        </div>

                        <a href="{{ route('equipment-movements.damages.create', ['equipmentMovement' => $equipmentMovement, 'item' => $item->id]) }}"
                           class="block pt-1 text-center text-[11px] font-semibold text-red-400 underline">
                            Reportar avaria neste item
                        </a>
                    </div>
                @endif
            </div>
        @empty
            <p class="pt-8 text-center text-sm text-zinc-500">Nenhum item de checklist vinculado a esta movimentação.</p>
        @endforelse
    </main>

    {{-- Rodapé / ações --}}
    <footer class="sticky bottom-0 flex gap-3 border-t border-zinc-800 bg-zinc-950 px-5 py-4">
        <button type="button" wire:click="back"
                class="min-h-[3.25rem] flex-1 rounded-xl border border-zinc-700 bg-zinc-900 text-sm font-bold tracking-wide text-zinc-300">
            VOLTAR
        </button>
        <button type="button" wire:click="finalize" @disabled($this->progress < 100)
                class="min-h-[3.25rem] flex-[1.4] rounded-xl bg-emerald-500 text-sm font-bold tracking-wide text-zinc-950 disabled:bg-zinc-800 disabled:text-zinc-600">
            FINALIZAR
        </button>
    </footer>
</div>
