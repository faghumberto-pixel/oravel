<div class="mx-auto flex min-h-screen max-w-md flex-col bg-zinc-50 text-zinc-900 md:h-full md:min-h-0 md:overflow-y-auto">
    {{-- Header --}}
    <header class="mb-4 flex items-center justify-between rounded-b-3xl bg-gradient-to-r from-blue-600 to-blue-500 px-5 pb-5 pt-6 text-white shadow-md">
        <h1 class="text-xs font-bold tracking-widest text-blue-100">MANUTENÇÃO PREVENTIVA</h1>
        <span class="text-xs font-bold tracking-wide text-white">{{ strtoupper(config('app.name', 'ORAVEL')) }}</span>
    </header>

    {{-- Bloco de identificação --}}
    <section class="px-5 pb-4">
        <h2 class="text-xl font-extrabold leading-tight text-zinc-900">
            OS Nº {{ $maintenanceOrder->os_number }}
        </h2>
        <p class="mt-1 text-sm font-medium text-zinc-600">
            {{ strtoupper($this->asset->name) }} · GRUPO: {{ strtoupper($this->asset->checklistGroup?->name ?? '—') }}
        </p>

        <div class="mt-3 flex items-center justify-between text-[11px] font-semibold text-zinc-600">
            <span>{{ $this->progress }}% executado</span>
            <span>{{ count($this->items) }} {{ count($this->items) === 1 ? 'item' : 'itens' }}</span>
        </div>
        <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-zinc-100">
            <div class="h-2 rounded-full bg-emerald-600 transition-all duration-300" style="width: {{ $this->progress }}%"></div>
        </div>

        <div class="mt-4 rounded-xl bg-white p-4 border border-zinc-200 shadow-sm">
            <label class="text-[11px] font-bold uppercase tracking-wide text-zinc-600">Horímetro Atual</label>
            <input type="number" step="0.01" wire:model="horimetro" placeholder="Ex: 1450"
                   class="mt-1 w-full rounded-xl border-0 bg-zinc-100 p-3 text-base text-zinc-900 placeholder:text-zinc-400 focus:ring-2 focus:ring-emerald-500">
            @error('horimetro')
                <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </section>

    {{-- Lista de itens --}}
    <main class="flex-1 space-y-2 overflow-y-auto px-5 pb-4">
        @forelse($this->items as $item)
            @php
                $plan = $item['plan'];
                $execution = $item['execution'];
                $status = $item['status'];
            @endphp
            <div class="overflow-hidden rounded-xl bg-white">
                <div class="flex w-full items-center gap-3 px-4 py-4">
                    <button type="button" wire:click="expand('{{ $plan->id }}')" @disabled(! $execution) class="flex flex-1 items-center gap-3 text-left disabled:opacity-60">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                             class="h-4 w-4 shrink-0 text-zinc-600 transition-transform {{ $expandedPlanId === $plan->id ? 'rotate-90' : '' }}">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" />
                        </svg>
                        <span class="block">
                            <span class="block text-sm font-semibold uppercase leading-snug text-zinc-900">{{ $plan->name }}</span>
                            <span class="block text-[11px] font-semibold {{ $status['is_overdue'] ? 'text-red-600' : 'text-zinc-600' }}">
                                @if($status['is_overdue'])
                                    VENCIDO há {{ number_format($status['overdue_hours'], 0) }}h
                                @else
                                    Próxima em {{ number_format($status['due_at_hours'] - (float) $this->asset->horimetro_atual, 0) }}h
                                @endif
                                (intervalo {{ $plan->interval_hours }}h)
                            </span>
                            @if($plan->notes)
                                <span class="block text-[11px] font-semibold text-amber-700">{{ $plan->notes }}</span>
                            @endif
                        </span>
                    </button>

                    <button type="button" wire:click="toggleItem('{{ $plan->id }}')"
                            class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full transition-colors {{ $execution ? 'bg-emerald-600 text-white' : 'bg-zinc-100 text-zinc-500' }}">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </button>
                </div>

                @if($expandedPlanId === $plan->id && $execution)
                    <div class="space-y-3 border-t border-zinc-200 px-4 py-4">
                        <p class="text-[11px] text-zinc-600">
                            Executado a {{ $execution->horimetro_at_execution }}h@if($execution->next_due_horimetro) · próxima previsão {{ $execution->next_due_horimetro }}h@endif
                        </p>

                        <textarea wire:model="newObservation" rows="3" placeholder="Estado da máquina / observações..."
                                  class="w-full rounded-xl border-0 bg-zinc-100 p-3 text-sm text-zinc-900 placeholder:text-zinc-400 focus:ring-2 focus:ring-emerald-500"></textarea>

                        @if($execution->getMedia('photos')->count())
                            <div class="flex flex-wrap gap-2">
                                @foreach($execution->getMedia('photos') as $media)
                                    <div class="relative h-16 w-16">
                                        <img src="{{ $media->getUrl('thumb') }}" class="h-16 w-16 rounded-lg object-cover">
                                        <button type="button" wire:click="removeMedia({{ $media->id }})"
                                                class="absolute -right-1.5 -top-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-bold text-white">
                                            &times;
                                        </button>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        <label class="flex min-h-[2.75rem] cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-zinc-300 text-xs font-semibold text-zinc-600">
                            <input type="file" accept="image/*" capture="environment" wire:model="newPhoto" class="hidden">
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
                        <div wire:loading wire:target="newPhoto" class="text-[11px] text-zinc-600">Enviando foto...</div>

                        <div class="flex gap-2 pt-1">
                            <button type="button" wire:click="collapse"
                                    class="min-h-[2.75rem] flex-1 rounded-xl border border-zinc-300 text-xs font-bold text-zinc-700">
                                CANCELAR
                            </button>
                            <button type="button" wire:click="saveItemDetails"
                                    class="min-h-[2.75rem] flex-1 rounded-xl bg-emerald-600 text-xs font-bold text-white">
                                SALVAR
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="pt-8 text-center text-sm text-zinc-600">Nenhum item de preventiva cadastrado para o grupo deste ativo.</p>
        @endforelse
    </main>

    {{-- Rodapé / ações --}}
    <footer class="sticky bottom-0 flex gap-3 border-t border-zinc-200 bg-white px-5 py-4">
        <button type="button" wire:click="back"
                class="min-h-[3.25rem] flex-1 rounded-xl border border-zinc-300 bg-white text-sm font-bold tracking-wide text-zinc-700">
            VOLTAR
        </button>
        <a href="{{ route('maintenance-orders.preventiva.print', $maintenanceOrder) }}" target="_blank"
           class="flex min-h-[3.25rem] flex-[1.4] items-center justify-center rounded-xl bg-emerald-600 text-sm font-bold tracking-wide text-white">
            IMPRIMIR
        </a>
    </footer>
</div>
