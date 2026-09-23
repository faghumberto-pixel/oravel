<div
    x-data="{
        open: @entangle('open'),
        activeIndex: @entangle('activeIndex'),
        init() {
            this.$watch('open', (value) => {
                if (value) {
                    this.$nextTick(() => this.$refs.screenSearchInput?.focus());
                } else {
                    $wire.set('query', '');
                }
            });
        },
    }"
    x-on:keydown.window="
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            open = true;
        }
    "
    class="relative"
>
    {{-- Gatilho no topbar -- ainda clicável, mas o atalho global (Cmd/Ctrl+K,
         acima) é o caminho principal, como em qualquer command palette. --}}
    <button
        type="button"
        x-on:click="open = true"
        class="flex h-9 items-center gap-1.5 rounded-lg px-2 text-gray-400 transition hover:bg-white/5 hover:text-gray-200"
        title="Buscar tela do sistema (Ctrl+K)"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <kbd class="hidden rounded border border-white/10 bg-white/5 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500 sm:inline">⌘K</kbd>
    </button>

    {{-- Teleportado pro final do <body>: o topbar tem stacking context/overflow
         próprios (ver bug documentado de "overflow-hidden footer trap" no
         login) -- um modal fixed dentro dele corre o risco de ficar cortado
         ou atrás de outra coisa. Fora dali, sempre por cima de tudo. --}}
    <template x-teleport="body">
        <div
            x-show="open"
            x-cloak
            x-on:keydown.escape.window="open = false"
            class="fixed inset-0 z-[100] flex items-start justify-center px-4 pt-[12vh]"
        >
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-on:click="open = false"
                class="absolute inset-0 bg-gray-950/60 backdrop-blur-sm"
            ></div>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 -translate-y-2 scale-[0.98]"
                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                x-on:click.outside="open = false"
                x-on:keydown.arrow-down.prevent="activeIndex = Math.min(activeIndex + 1, ($refs.resultsList?.children.length ?? 1) - 1)"
                x-on:keydown.arrow-up.prevent="activeIndex = Math.max(activeIndex - 1, 0)"
                x-on:keydown.enter.prevent="$refs.resultsList?.children[activeIndex]?.querySelector('a')?.click()"
                class="relative w-full max-w-xl overflow-hidden rounded-xl bg-gray-900 shadow-2xl ring-1 ring-white/10"
            >
                <div class="flex items-center gap-2 border-b border-white/10 px-4 py-3">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5 shrink-0 text-gray-500">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <input
                        x-ref="screenSearchInput"
                        type="text"
                        wire:model.live.debounce.150ms="query"
                        placeholder="Buscar tela... (ex: planta baixa, ordens de serviço)"
                        class="w-full border-0 bg-transparent p-0 text-sm text-white placeholder-gray-500 focus:ring-0"
                        autocomplete="off"
                    >
                    <kbd class="shrink-0 rounded border border-white/10 bg-white/5 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500">Esc</kbd>
                </div>

                <div class="max-h-80 overflow-y-auto p-2">
                    @if (trim($query) === '')
                        <p class="px-2 py-6 text-center text-sm text-gray-500">Digite pra buscar qualquer tela do sistema.</p>
                    @else
                        <ul x-ref="resultsList">
                            @forelse ($this->results as $index => $item)
                                <li>
                                    {{-- Destaque via Alpine puro (x-bind:class), nao a classe vinda do PHP
                                         ($activeIndex) -- essa so' atualiza depois de um round-trip do
                                         Livewire (debounce), o que deixava a seta do teclado com
                                         "atraso" visual perceptivel; comparar direto o activeIndex local
                                         responde na hora. --}}
                                    <a
                                        href="{{ $item['url'] }}"
                                        x-on:mouseenter="activeIndex = {{ $index }}"
                                        x-bind:class="activeIndex === {{ $index }} ? 'bg-white/10' : ''"
                                        class="block truncate rounded-lg px-3 py-2 text-sm text-gray-200 transition"
                                    >
                                        {{ $item['label'] }}
                                    </a>
                                </li>
                            @empty
                                <li class="px-2 py-6 text-center text-sm text-gray-500">Nenhuma tela encontrada.</li>
                            @endforelse
                        </ul>
                    @endif
                </div>

                <div class="flex items-center gap-3 border-t border-white/10 px-4 py-2 text-[11px] text-gray-500">
                    <span class="flex items-center gap-1"><kbd class="rounded border border-white/10 bg-white/5 px-1 py-0.5">↑↓</kbd> navegar</span>
                    <span class="flex items-center gap-1"><kbd class="rounded border border-white/10 bg-white/5 px-1 py-0.5">⏎</kbd> abrir</span>
                    <span class="flex items-center gap-1"><kbd class="rounded border border-white/10 bg-white/5 px-1 py-0.5">esc</kbd> fechar</span>
                </div>
            </div>
        </div>
    </template>
</div>
