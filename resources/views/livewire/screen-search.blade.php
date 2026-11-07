{{-- Campo unico de pesquisa no topbar (substitui a lupa que abria um modal e a pesquisa global nativa do
     Filament, que nunca achava nada). Digitou, aparece a lista de telas logo abaixo do campo; Ctrl/Cmd+K
     foca o campo; setas e Enter navegam; Esc limpa. Tamanhos via style="" de proposito: o CSS do tema e'
     compilado e classes novas so' existiriam depois de um build. --}}
<div
    x-data="{
        aberto: false,
        activeIndex: @entangle('activeIndex'),
        fechar() { this.aberto = false; },
    }"
    x-on:keydown.window="
        if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
            event.preventDefault();
            $refs.screenSearchInput.focus();
            $refs.screenSearchInput.select();
        }
    "
    x-on:click.outside="fechar()"
    style="position: relative; width: clamp(9rem, 30vw, 24rem);"
>
<style>
    .ss-painel { position: absolute; right: 0; top: calc(100% + .5rem); z-index: 60; width: max(100%, 22rem); max-height: 20rem; overflow-y: auto; padding: .5rem; }
    @media (max-width: 640px) { .ss-painel { position: fixed; left: 1rem; right: 1rem; top: 4.25rem; width: auto; } }
</style>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5 text-gray-400" style="position: absolute; left: .75rem; top: 50%; transform: translateY(-50%); pointer-events: none;">
        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
    </svg>
    <input
        x-ref="screenSearchInput"
        type="search"
        wire:model.live.debounce.150ms="query"
        x-on:focus="aberto = true"
        x-on:input="aberto = true"
        x-on:keydown.escape="$wire.set('query', ''); fechar(); $refs.screenSearchInput.blur()"
        x-on:keydown.arrow-down.prevent="activeIndex = Math.min(activeIndex + 1, ($refs.resultsList?.children.length ?? 1) - 1)"
        x-on:keydown.arrow-up.prevent="activeIndex = Math.max(activeIndex - 1, 0)"
        x-on:keydown.enter.prevent="$refs.resultsList?.children[activeIndex]?.querySelector('a')?.click()"
        placeholder="Pesquisar tela... (Ctrl+K)"
        title="Pesquisar qualquer tela do sistema (Ctrl+K)"
        autocomplete="off"
        class="text-sm text-white placeholder-gray-400 focus:ring-2"
        style="width: 100%; height: 2.25rem; padding: 0 .75rem 0 2.5rem; border-radius: .5rem; background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.22);"
    >

    <div
        x-show="aberto && $wire.query.trim() !== ''"
        x-cloak
        class="ss-painel rounded-xl bg-gray-900 shadow-2xl ring-1 ring-white/10"
    >
        <ul x-ref="resultsList">
            @forelse ($this->results as $index => $item)
                <li>
                    {{-- Destaque via Alpine puro (sem round-trip do Livewire) para a seta do teclado responder na hora. --}}
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
                <li class="px-2 py-4 text-center text-sm text-gray-500">Nenhuma tela encontrada.</li>
            @endforelse
        </ul>
    </div>
</div>
