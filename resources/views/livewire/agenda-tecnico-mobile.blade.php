{{--
    Agenda Técnica — próximos 30 dias, design polido mobile-first.
--}}

<div class="fixed inset-0 mx-auto flex max-w-md flex-col bg-white dark:bg-slate-950">
    {{-- Header polido --}}
    <header class="sticky top-0 z-40 border-b border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/95 backdrop-blur px-5 py-4">
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Próximos 30 dias</p>
                    <h1 class="text-2xl font-black text-slate-900 dark:text-white mt-1">Programação</h1>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-blue-600 text-xs font-bold text-white shadow-lg">
                    {{ strtoupper(mb_substr(Auth::user()?->name ?? '?', 0, 1)) }}
                </div>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <div class="h-2 w-2 rounded-full bg-blue-500"></div>
                <span class="font-semibold text-slate-700 dark:text-slate-300">{{ count($this->agendaItems) }} agendamentos</span>
            </div>
        </div>
    </header>

    {{-- Busca + Filtros --}}
    <div class="border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 px-4 py-3 space-y-3">
        {{-- Campo de busca --}}
        <div class="relative">
            <input wire:model.live="search"
                   type="text"
                   placeholder="Buscar..."
                   class="w-full rounded-lg border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-800 px-4 py-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">🔍</span>
        </div>

        {{-- Filtros tipo --}}
        <div class="flex gap-2">
            <button wire:click="$set('filterType', '')"
                    :class="$filterType === '' ? 'bg-blue-500 text-white shadow-md' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                    class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition active:scale-95">
                Todos
            </button>
            <button wire:click="$set('filterType', 'appointment')"
                    :class="$filterType === 'appointment' ? 'bg-blue-500 text-white shadow-md' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                    class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition active:scale-95">
                Agendamentos
            </button>
            <button wire:click="$set('filterType', 'order')"
                    :class="$filterType === 'order' ? 'bg-blue-500 text-white shadow-md' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                    class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition active:scale-95">
                O.S.
            </button>
        </div>

        {{-- Ordenação --}}
        <div class="flex gap-2">
            <button wire:click="$set('sortBy', 'date')"
                    :class="$sortBy === 'date' ? 'bg-slate-600 dark:bg-slate-700 text-white' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                    class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition active:scale-95">
                Por Data
            </button>
            <button wire:click="$set('sortBy', 'urgency')"
                    :class="$sortBy === 'urgency' ? 'bg-slate-600 dark:bg-slate-700 text-white' : 'bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300'"
                    class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition active:scale-95">
                Por Urgência
            </button>
        </div>
    </div>

    {{-- Lista de agendamentos --}}
    <main class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
        @forelse ($this->agendaItems as $item)
            <div @if($item['type'] === 'order')
                    onclick="window.location.href='{{ $item['url'] }}'"
                @endif
                class="rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden @if($item['type'] === 'order') cursor-pointer transition active:scale-95 hover:shadow-lg @endif">
                <div class="p-4 space-y-2">
                    {{-- Header: Ícone, tipo, urgência --}}
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-slate-100 dark:bg-slate-700 flex items-center justify-center text-lg">
                                {{ $item['icon'] }}
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase">{{ $item['label'] }}</p>
                                <p class="text-xs text-slate-600 dark:text-slate-300">{{ $item['date'] }} às {{ $item['time'] }}</p>
                            </div>
                        </div>
                        @if ($item['urgente'] ?? false)
                            <span class="inline-block rounded-full bg-red-500 text-white px-2 py-1 text-[10px] font-bold">
                                URGENTE
                            </span>
                        @endif
                    </div>

                    {{-- Título --}}
                    <h3 class="text-base font-bold text-slate-900 dark:text-white line-clamp-2">
                        {{ $item['title'] }}
                    </h3>

                    {{-- Descrição --}}
                    @if ($item['description'])
                        <p class="text-sm text-slate-600 dark:text-slate-400 line-clamp-2">
                            {{ $item['description'] }}
                        </p>
                    @endif

                    {{-- Detalhes (PAT, Cliente) --}}
                    <div class="space-y-1 text-xs">
                        @if ($item['type'] === 'order' && $item['patrimonio'])
                            <p class="text-slate-600 dark:text-slate-400">
                                Patrimônio: <span class="font-semibold text-slate-900 dark:text-white"># {{ $item['patrimonio'] }}</span>
                            </p>
                        @endif
                        @if ($item['client'] ?? false)
                            <p class="text-slate-600 dark:text-slate-400">
                                Local: <span class="font-semibold text-slate-900 dark:text-white">📍 {{ $item['client'] }}</span>
                            </p>
                        @endif
                    </div>

                    {{-- Status para O.S. --}}
                    @if ($item['type'] === 'order')
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-700">
                            <span class="inline-block {{ $item['color'] }} px-3 py-1 text-xs font-semibold rounded-full">
                                {{ $item['status'] }}
                            </span>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="flex h-96 items-center justify-center">
                <div class="text-center">
                    <p class="text-4xl mb-2">🎉</p>
                    <p class="text-base font-bold text-slate-900 dark:text-white">Sem agendamentos</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Você está em dia nos próximos 30 dias</p>
                </div>
            </div>
        @endforelse
        <div class="h-20"></div>
    </main>

    {{-- Botão fixo no fundo --}}
    <footer class="sticky bottom-0 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900/95 backdrop-blur px-4 py-3">
        <a href="{{ route('filament.admin.pages.agenda-tecnico') }}"
           class="block w-full rounded-xl bg-gradient-to-r from-blue-500 to-blue-600 text-white px-4 py-3 text-center font-bold text-base transition active:scale-95 hover:shadow-lg shadow-lg">
            Ver Calendário Completo
        </a>
    </footer>
</div>
