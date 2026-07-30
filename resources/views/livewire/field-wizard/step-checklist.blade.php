<div class="flex-1 space-y-2 overflow-y-auto px-5 pb-4">
    @forelse($this->items as $index => $item)
        <div class="overflow-hidden rounded-2xl bg-zinc-900">
            <div class="flex w-full items-center gap-3 px-4 py-4">
                <button type="button" wire:click="expand('{{ $item->id }}')" class="flex flex-1 items-center gap-3 text-left">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                         class="h-4 w-4 shrink-0 text-zinc-600 transition-transform {{ $expandedItemId === $item->id ? 'rotate-90' : '' }}">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 6l6 6-6 6" />
                    </svg>
                    <span class="text-sm font-semibold uppercase leading-snug text-zinc-100">
                        {{ $index + 1 }}. {{ $item->item_name ?: $item->category }}
                    </span>
                </button>

                <div class="flex items-center gap-2">
                    <button type="button" wire:click="setItemStatus('{{ $item->id }}', 'conforme')"
                            class="flex h-9 items-center justify-center rounded-lg px-2 transition-colors {{ $item->status === 'conforme' ? 'bg-emerald-500 text-zinc-950 text-[10px] font-bold' : 'border border-zinc-700 text-[10px] font-bold text-zinc-500' }}"
                            title="Conforme">
                        ✓
                    </button>
                    <button type="button" wire:click="setItemStatus('{{ $item->id }}', 'nao_conforme')"
                            class="flex h-9 items-center justify-center rounded-lg px-2 transition-colors {{ $item->status === 'nao_conforme' ? 'bg-red-500 text-zinc-950 text-[10px] font-bold' : 'border border-zinc-700 text-[10px] font-bold text-zinc-500' }}"
                            title="Não Conforme">
                        ✗
                    </button>
                    <button type="button" wire:click="setItemStatus('{{ $item->id }}', 'nao_aplicavel')"
                            class="flex h-9 items-center justify-center rounded-lg px-2 transition-colors {{ $item->status === 'nao_aplicavel' ? 'bg-amber-500 text-zinc-950 text-[10px] font-bold' : 'border border-zinc-700 text-[10px] font-bold text-zinc-500' }}"
                            title="Não Aplicável">
                        —
                    </button>
                </div>
            </div>

            @if($expandedItemId === $item->id)
                <div class="space-y-3 border-t border-zinc-800 px-4 py-4">
                    @error('itemStatusError')
                        <p class="text-[11px] text-red-400">{{ $message }}</p>
                    @enderror

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
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <label class="flex min-h-[2.75rem] cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-zinc-700 text-xs font-semibold text-zinc-400">
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
                </div>
            @endif
        </div>
    @empty
        <p class="pt-8 text-center text-sm text-zinc-500">Nenhum item de checklist vinculado a esta OS.</p>
    @endforelse
</div>
