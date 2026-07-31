{{--
    "Minhas Ordens de Serviço" — lista do dia do técnico.
    Otimizado para mobile (375-430px), offline-first, com filtros rápidos.
--}}

<div class="mx-auto flex min-h-screen max-w-md flex-col bg-zinc-950">
    {{-- Cabeçalho fixo --}}
    <header class="sticky top-0 z-40 border-b border-zinc-800 bg-zinc-900 px-5 py-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-xs font-bold tracking-widest text-zinc-400">MINHAS ORDENS</h1>
                <p class="mt-1 text-sm font-semibold text-zinc-100">
                    {{ $this->pendingCount }} pendentes • {{ $this->completedCount }} completas
                </p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-800 text-xs font-bold text-zinc-300">
                {{ strtoupper(mb_substr(Auth::user()?->name ?? '?', 0, 1)) }}
            </div>
        </div>

        {{-- Indicador de sincronização --}}
        <div class="mt-3 flex items-center gap-2 text-[11px] font-semibold text-zinc-400">
            <span class="inline-block h-2 w-2 rounded-full bg-green-500"></span>
            Sincronizado agora
        </div>
    </header>

    {{-- Filtros rápidos (chips) --}}
    <div class="border-b border-zinc-800 bg-zinc-900 px-3 py-3">
        <div class="space-y-2 text-[11px]">
            {{-- Tipo de tarefa --}}
            <div class="flex gap-1 overflow-x-auto">
                <button wire:click="$set('filterType', '')"
                        wire:loading.attr="disabled"
                        :class="$filterType === '' ? 'bg-emerald-600 text-zinc-950' : 'bg-zinc-800 text-zinc-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold transition hover:bg-emerald-600 hover:text-zinc-950">
                    Todos
                </button>
                <button wire:click="$set('filterType', 'maintenance')"
                        wire:loading.attr="disabled"
                        :class="$filterType === 'maintenance' ? 'bg-emerald-600 text-zinc-950' : 'bg-zinc-800 text-zinc-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold transition hover:bg-emerald-600 hover:text-zinc-950">
                    🏭 Manutencao
                </button>
                <button wire:click="$set('filterType', 'mobilization')"
                        wire:loading.attr="disabled"
                        :class="$filterType === 'mobilization' ? 'bg-emerald-600 text-zinc-950' : 'bg-zinc-800 text-zinc-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold transition hover:bg-emerald-600 hover:text-zinc-950">
                    🚚 Mobilizacao
                </button>
                <button wire:click="$set('filterType', 'demobilization')"
                        wire:loading.attr="disabled"
                        :class="$filterType === 'demobilization' ? 'bg-emerald-600 text-zinc-950' : 'bg-zinc-800 text-zinc-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold transition hover:bg-emerald-600 hover:text-zinc-950">
                    🔄 Desmob
                </button>
            </div>

            {{-- Criticidade ABC --}}
            <div class="flex gap-1">
                <button wire:click="$set('filterCriticality', '')"
                        wire:loading.attr="disabled"
                        :class="$filterCriticality === '' ? 'bg-emerald-600 text-zinc-950' : 'bg-zinc-800 text-zinc-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold transition hover:bg-emerald-600 hover:text-zinc-950">
                    Prioridade
                </button>
                <button wire:click="$set('filterCriticality', 'A')"
                        wire:loading.attr="disabled"
                        :class="$filterCriticality === 'A' ? 'bg-red-600 text-zinc-950' : 'bg-red-900/30 text-red-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold">
                    A
                </button>
                <button wire:click="$set('filterCriticality', 'B')"
                        wire:loading.attr="disabled"
                        :class="$filterCriticality === 'B' ? 'bg-yellow-600 text-zinc-950' : 'bg-yellow-900/30 text-yellow-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold">
                    B
                </button>
                <button wire:click="$set('filterCriticality', 'C')"
                        wire:loading.attr="disabled"
                        :class="$filterCriticality === 'C' ? 'bg-green-600 text-zinc-950' : 'bg-green-900/30 text-green-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold">
                    C
                </button>
            </div>

            {{-- Natureza (Interno/Externo) --}}
            <div class="flex gap-1">
                <button wire:click="$set('filterNature', '')"
                        wire:loading.attr="disabled"
                        :class="$filterNature === '' ? 'bg-emerald-600 text-zinc-950' : 'bg-zinc-800 text-zinc-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold transition hover:bg-emerald-600 hover:text-zinc-950">
                    Natureza
                </button>
                <button wire:click="$set('filterNature', 'internal')"
                        wire:loading.attr="disabled"
                        :class="$filterNature === 'internal' ? 'bg-blue-600 text-zinc-950' : 'bg-blue-900/30 text-blue-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold">
                    🏭 Interno
                </button>
                <button wire:click="$set('filterNature', 'external')"
                        wire:loading.attr="disabled"
                        :class="$filterNature === 'external' ? 'bg-purple-600 text-zinc-950' : 'bg-purple-900/30 text-purple-400'"
                        class="shrink-0 rounded-full px-3 py-1 font-semibold">
                    🌐 Externo
                </button>
            </div>
        </div>
    </div>

    {{-- Lista de tarefas --}}
    <main class="flex-1 overflow-y-auto">
        @forelse ($this->technicianTasks as $task)
            <a href="{{ $task['url'] }}" class="block border-b border-zinc-800 p-4 transition active:bg-zinc-900">
                {{-- Badge de tipo --}}
                <div class="mb-2 inline-block rounded-full bg-zinc-800 px-2 py-0.5 text-[10px] font-bold uppercase text-zinc-300">
                    {{ $task['label'] }}
                </div>

                {{-- Informações principais --}}
                <h3 class="font-semibold text-zinc-100">
                    {{ $task['asset_name'] }}
                </h3>
                <p class="mt-1 text-sm text-zinc-400">
                    PAT. {{ $task['patrimonio'] }}
                </p>

                {{-- Cliente (se Externo) --}}
                @if ($task['client'])
                    <p class="mt-1 text-[11px] text-zinc-500">
                        📍 {{ $task['client'] }}
                    </p>
                @endif

                {{-- Criticidade + Status --}}
                <div class="mt-3 flex items-center justify-between">
                    <span class="inline-block rounded-full px-2 py-0.5 text-xs font-bold"
                          @php
                            $criticality = $task['criticality'];
                            $classes = $criticality === 'A' ? 'bg-red-900/30 text-red-400' : ($criticality === 'B' ? 'bg-yellow-900/30 text-yellow-400' : 'bg-green-900/30 text-green-400');
                          @endphp
                          class="{{ $classes }}">
                        {{ $task['criticality'] }}
                    </span>
                    <span class="text-[11px] font-semibold"
                          @php
                            $status = $task['status'];
                            $statusClasses = $status === 'synced' ? 'text-green-400' : ($status === 'pending' ? 'text-amber-400' : 'text-zinc-400');
                            $statusLabel = $status === 'synced' ? '✓ Sincronizado' : ($status === 'pending' ? '⏱ Pendente' : '— ' . ucfirst($status));
                          @endphp
                          class="{{ $statusClasses }}">
                        {{ $statusLabel }}
                    </span>
                </div>
            </a>
        @empty
            <div class="flex h-96 items-center justify-center">
                <div class="text-center">
                    <p class="text-sm text-zinc-400">Nenhuma tarefa para hoje.</p>
                    <p class="mt-1 text-[11px] text-zinc-600">Volte mais tarde ou sincronize.</p>
                </div>
            </div>
        @endforelse
    </main>

    {{-- Rodapé fixo: ações --}}
    <footer class="sticky bottom-0 border-t border-zinc-800 bg-zinc-900 px-5 pb-4 pt-3">
        <div class="flex gap-3">
            <button wire:click="$dispatch('refresh-technician-tasks')"
                    wire:loading.attr="disabled"
                    class="flex-1 rounded-xl border border-zinc-700 bg-zinc-800 px-3 py-3 text-sm font-bold text-zinc-300 hover:bg-zinc-700 disabled:opacity-50">
                🔄 Sincronizar
            </button>
            <a href="{{ route('filament.admin.dashboard') }}"
               class="flex-1 rounded-xl bg-emerald-600 px-3 py-3 text-center text-sm font-bold text-zinc-950 hover:bg-emerald-500">
                ⚙️ Painel
            </a>
        </div>
    </footer>
</div>
