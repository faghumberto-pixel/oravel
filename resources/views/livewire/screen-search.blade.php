<div
    x-data="{ open: @entangle('open') }"
    x-on:click.outside="open = false; $wire.set('query', '')"
    class="relative"
>
    <button
        type="button"
        x-on:click="open = ! open; open && $nextTick(() => $refs.screenSearchInput.focus())"
        class="flex h-9 w-9 items-center justify-center rounded-lg text-gray-400 transition hover:bg-white/5 hover:text-gray-200"
        title="Buscar tela do sistema"
    >
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
    </button>

    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="absolute end-0 top-full z-30 mt-2 w-72 rounded-lg bg-gray-900 p-2 shadow-lg ring-1 ring-white/10"
    >
        <input
            x-ref="screenSearchInput"
            type="text"
            wire:model.live.debounce.200ms="query"
            placeholder="Buscar tela... (ex: planta baixa)"
            class="w-full rounded-md border-gray-700 bg-gray-800 text-sm text-white placeholder-gray-500 focus:border-primary-500 focus:ring-primary-500"
        >

        @if (trim($query) !== '')
            <ul class="mt-2 max-h-72 overflow-y-auto">
                @forelse ($this->results as $item)
                    <li>
                        <a
                            href="{{ $item['url'] }}"
                            class="block truncate rounded-md px-2 py-1.5 text-sm text-gray-200 hover:bg-white/10"
                        >
                            {{ $item['label'] }}
                        </a>
                    </li>
                @empty
                    <li class="px-2 py-1.5 text-sm text-gray-500">Nenhuma tela encontrada.</li>
                @endforelse
            </ul>
        @endif
    </div>
</div>
