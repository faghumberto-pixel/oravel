{{--
    Agenda Técnica — próximos 30 dias de compromissos e O.S. agendadas.
    Otimizado para mobile (375-430px), lista simples com filtros rápidos.
--}}

<div class="mx-auto flex min-h-screen max-w-md flex-col bg-zinc-950">
    {{-- Cabeçalho fixo --}}
    <header class="sticky top-0 z-40 border-b border-zinc-800 bg-zinc-900 px-5 py-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xs font-bold tracking-widest text-zinc-400">MINHA AGENDA</h1>
                <p class="mt-1 text-sm font-semibold text-zinc-100">
                    {{ count($this->agendaItems) }} agendamentos
                </p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-800 text-xs font-bold text-zinc-300">
                {{ strtoupper(mb_substr(Auth::user()?->name ?? '?', 0, 1)) }}
            </div>
        </div>

        {{-- Campo de busca --}}
        <div class="mt-3">
            <input wire:model.live="search"
                   type="text"
                   placeholder="Buscar agendamento..."
                   class="w-full rounded-lg border border-zinc-700 bg-zinc-800 px-3 py-2 text-xs text-zinc-100 placeholder-zinc-500 focus:border-emerald-600 focus:ring-emerald-600">
        </div>
    </header>

    {{-- Filtros rápidos (chips) --}}
    <div class="border-b border-zinc-800 bg-zinc-900 px-3 py-3">
        <div class="space-y-2 text-[11px]">
            {{-- Tipo de agendamento --}}
            <div class="flex gap-1 overflow-x-auto">
                <button wire:click="$set('filterType', '')"
                        wire:loading.attr="disabled"
                        :class="$filterType === '' ? 'bg-emerald-600 text-zinc-950' : 'bg-zinc-800 text-zinc-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold transition hover:bg-emerald-600 hover:text-zinc-950">
                    Todos
                </button>
                <button wire:click="$set('filterType', 'appointment')"
                        wire:loading.attr="disabled"
                        :class="$filterType === 'appointment' ? 'bg-emerald-600 text-zinc-950' : 'bg-zinc-800 text-zinc-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold transition hover:bg-emerald-600 hover:text-zinc-950">
                    📅 Agendamentos
                </button>
                <button wire:click="$set('filterType', 'order')"
                        wire:loading.attr="disabled"
                        :class="$filterType === 'order' ? 'bg-emerald-600 text-zinc-950' : 'bg-zinc-800 text-zinc-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold transition hover:bg-emerald-600 hover:text-zinc-950">
                    🔧 O.S. Agendadas
                </button>
            </div>

            {{-- Ordenação --}}
            <div class="flex gap-1">
                <button wire:click="$set('sortBy', 'date')"
                        wire:loading.attr="disabled"
                        :class="$sortBy === 'date' ? 'bg-emerald-600 text-zinc-950' : 'bg-zinc-800 text-zinc-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold transition hover:bg-emerald-600 hover:text-zinc-950">
                    ↓ Data
                </button>
                <button wire:click="$set('sortBy', 'urgency')"
                        wire:loading.attr="disabled"
                        :class="$sortBy === 'urgency' ? 'bg-emerald-600 text-zinc-950' : 'bg-zinc-800 text-zinc-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold transition hover:bg-emerald-600 hover:text-zinc-950">
                    🚨 Urgência
                </button>
            </div>
        </div>
    </div>

    {{-- Lista de agendamentos --}}
    <main class="flex-1 overflow-y-auto">
        @forelse ($this->agendaItems as $item)
            <div class="border-b border-zinc-800 p-4 transition active:bg-zinc-900
                @if($item['type'] === 'order') cursor-pointer hover:bg-zinc-800/50 @endif">

                {{-- Tipo de item --}}
                <div class="mb-2 flex items-center gap-2">
                    <span class="text-lg">{{ $item['icon'] }}</span>
                    <span class="inline-block rounded-full bg-zinc-800 px-2 py-0.5 text-[10px] font-bold uppercase text-zinc-300">
                        {{ $item['label'] }}
                    </span>
                    @if ($item['urgente'] ?? false)
                        <span class="inline-block rounded-full bg-red-900/30 px-2 py-0.5 text-[10px] font-bold text-red-400">
                            🚨 URGENTE
                        </span>
                    @endif
                </div>

                {{-- Título/Assunto --}}
                <h3 class="font-semibold text-zinc-100 line-clamp-2">
                    {{ $item['title'] }}
                </h3>

                {{-- Descrição (se houver) --}}
                @if ($item['description'])
                    <p class="mt-1 text-xs text-zinc-400 line-clamp-2">
                        {{ $item['description'] }}
                    </p>
                @endif

                {{-- Patrimônio (só para O.S.) --}}
                @if ($item['type'] === 'order' && $item['patrimonio'])
                    <p class="mt-1 text-xs text-zinc-500">
                        PAT. {{ $item['patrimonio'] }}
                    </p>
                @endif

                {{-- Cliente (se externo) --}}
                @if ($item['client'] ?? false)
                    <p class="mt-1 text-[11px] text-zinc-500">
                        📍 {{ $item['client'] }}
                    </p>
                @endif

                {{-- Data, hora e status --}}
                <div class="mt-3 flex items-center justify-between">
                    <span class="text-xs font-semibold text-zinc-300">
                        📆 {{ $item['date'] }} às {{ $item['time'] }}
                    </span>
                    @if ($item['type'] === 'order')
                        <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-bold {{ $item['color'] }}">
                            {{ $item['status'] }}
                        </span>
                    @endif
                </div>

                {{-- Link para O.S. (clique aqui) --}}
                @if ($item['type'] === 'order')
                    <a href="{{ $item['url'] }}" class="absolute inset-0"></a>
                @endif
            </div>
        @empty
            <div class="flex h-96 items-center justify-center">
                <div class="text-center">
                    <p class="text-sm text-zinc-400">Nenhum agendamento nos próximos 30 dias.</p>
                    <p class="mt-1 text-[11px] text-zinc-600">Você está em dia! 🎉</p>
                </div>
            </div>
        @endforelse
    </main>

    {{-- Rodapé fixo: ações --}}
    <footer class="sticky bottom-0 border-t border-zinc-800 bg-zinc-900 px-5 pb-4 pt-3">
        <div class="flex gap-3">
            <a href="{{ route('filament.admin.pages.agenda-tecnico') }}"
               class="flex-1 rounded-xl border border-zinc-700 bg-zinc-800 px-3 py-3 text-center text-sm font-bold text-zinc-300 hover:bg-zinc-700">
                📅 Calendário
            </a>
            <a href="{{ route('filament.admin.pages.painel-gestao', ['tenant' => auth()->user()->latest_tenant_slug]) }}"
               class="flex-1 rounded-xl bg-emerald-600 px-3 py-3 text-center text-sm font-bold text-zinc-950 hover:bg-emerald-500">
                ⚙️ Menu
            </a>
        </div>
    </footer>
</div>
