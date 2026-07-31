{{--
    "Minhas Ordens de Serviço" — lista do dia do técnico.
    Otimizado para mobile (375-430px), offline-first, com filtros rápidos.
    Design: Clean vertical PWA interface para uso em campo.
--}}

<div class="mx-auto flex min-h-screen max-w-md flex-col bg-zinc-950">
    {{-- Cabeçalho fixo --}}
    <header class="sticky top-0 z-40 border-b-2 border-zinc-800 bg-gradient-to-b from-zinc-900 to-zinc-950 px-4 py-3">
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <h1 class="text-lg font-bold tracking-tight text-white">MINHAS ORDENS DO DIA</h1>
                <p class="mt-1 text-xs font-semibold text-zinc-300">
                    {{ $this->pendingCount }} pendentes
                </p>
            </div>
            <div class="flex items-center gap-2">
                {{-- Status Offline --}}
                <div class="flex flex-col items-center gap-1">
                    <div class="text-xl">☁️</div>
                    <span class="text-[10px] font-bold text-zinc-400">Offline</span>
                </div>
                {{-- Avatar --}}
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-600 text-sm font-bold text-zinc-950">
                    {{ strtoupper(mb_substr(Auth::user()?->name ?? '?', 0, 1)) }}
                </div>
            </div>
        </div>
    </header>

    {{-- Filtros rápidos (chips tactilmente grandes) --}}
    <nav class="border-b-2 border-zinc-700 bg-zinc-900 px-3 py-3 space-y-3">
        {{-- Linha 1: Tipo de tarefa --}}
        <div>
            <p class="text-[10px] font-bold uppercase text-zinc-500 mb-2">Tipo de Tarefa</p>
            <div class="flex gap-2 overflow-x-auto">
                <button wire:click="$set('filterType', '')"
                        wire:loading.attr="disabled"
                        :class="$filterType === '' ? 'bg-emerald-600 text-zinc-950' : 'border-2 border-zinc-700 bg-zinc-800 text-zinc-300'"
                        class="shrink-0 rounded-lg px-3 py-2 text-xs font-bold transition active:scale-95">
                    Todos
                </button>
                <button wire:click="$set('filterType', 'maintenance')"
                        wire:loading.attr="disabled"
                        :class="$filterType === 'maintenance' ? 'bg-emerald-600 text-zinc-950' : 'border-2 border-zinc-700 bg-zinc-800 text-zinc-300'"
                        class="shrink-0 rounded-lg px-3 py-2 text-xs font-bold transition active:scale-95">
                    Manutenção
                </button>
                <button wire:click="$set('filterType', 'mobilization')"
                        wire:loading.attr="disabled"
                        :class="$filterType === 'mobilization' ? 'bg-emerald-600 text-zinc-950' : 'border-2 border-zinc-700 bg-zinc-800 text-zinc-300'"
                        class="shrink-0 rounded-lg px-3 py-2 text-xs font-bold transition active:scale-95">
                    Mobilização
                </button>
                <button wire:click="$set('filterType', 'demobilization')"
                        wire:loading.attr="disabled"
                        :class="$filterType === 'demobilization' ? 'bg-emerald-600 text-zinc-950' : 'border-2 border-zinc-700 bg-zinc-800 text-zinc-300'"
                        class="shrink-0 rounded-lg px-3 py-2 text-xs font-bold transition active:scale-95">
                    Desmobilização
                </button>
            </div>
        </div>

        {{-- Linha 2: Prioridade ABC --}}
        <div>
            <p class="text-[10px] font-bold uppercase text-zinc-500 mb-2">Prioridade</p>
            <div class="flex gap-2">
                <button wire:click="$set('filterCriticality', '')"
                        wire:loading.attr="disabled"
                        :class="$filterCriticality === '' ? 'bg-emerald-600 text-zinc-950' : 'border-2 border-zinc-700 bg-zinc-800 text-zinc-300'"
                        class="flex-1 rounded-lg px-2 py-2 text-xs font-bold transition active:scale-95">
                    Todas
                </button>
                <button wire:click="$set('filterCriticality', 'A')"
                        wire:loading.attr="disabled"
                        :class="$filterCriticality === 'A' ? 'bg-red-600 text-white' : 'border-2 border-red-900/40 bg-red-900/20 text-red-300'"
                        class="rounded-lg px-3 py-2 text-xs font-black transition active:scale-95">
                    Alta
                </button>
                <button wire:click="$set('filterCriticality', 'B')"
                        wire:loading.attr="disabled"
                        :class="$filterCriticality === 'B' ? 'bg-yellow-600 text-zinc-950' : 'border-2 border-yellow-900/40 bg-yellow-900/20 text-yellow-300'"
                        class="rounded-lg px-3 py-2 text-xs font-black transition active:scale-95">
                    Média
                </button>
                <button wire:click="$set('filterCriticality', 'C')"
                        wire:loading.attr="disabled"
                        :class="$filterCriticality === 'C' ? 'bg-green-600 text-zinc-950' : 'border-2 border-green-900/40 bg-green-900/20 text-green-300'"
                        class="rounded-lg px-3 py-2 text-xs font-black transition active:scale-95">
                    Baixa
                </button>
            </div>
        </div>

        {{-- Linha 3: Natureza --}}
        <div>
            <p class="text-[10px] font-bold uppercase text-zinc-500 mb-2">Localização</p>
            <div class="flex gap-2">
                <button wire:click="$set('filterNature', '')"
                        wire:loading.attr="disabled"
                        :class="$filterNature === '' ? 'bg-emerald-600 text-zinc-950' : 'border-2 border-zinc-700 bg-zinc-800 text-zinc-300'"
                        class="flex-1 rounded-lg px-2 py-2 text-xs font-bold transition active:scale-95">
                    Ambas
                </button>
                <button wire:click="$set('filterNature', 'internal')"
                        wire:loading.attr="disabled"
                        :class="$filterNature === 'internal' ? 'bg-blue-600 text-white' : 'border-2 border-blue-900/40 bg-blue-900/20 text-blue-300'"
                        class="flex-1 rounded-lg px-2 py-2 text-xs font-bold transition active:scale-95">
                    Interna
                </button>
                <button wire:click="$set('filterNature', 'external')"
                        wire:loading.attr="disabled"
                        :class="$filterNature === 'external' ? 'bg-purple-600 text-white' : 'border-2 border-purple-900/40 bg-purple-900/20 text-purple-300'"
                        class="flex-1 rounded-lg px-2 py-2 text-xs font-bold transition active:scale-95">
                    Externa
                </button>
            </div>
        </div>
    </nav>

    {{-- Lista de tarefas (large touch targets) --}}
    <main class="flex-1 overflow-y-auto px-3 py-4 space-y-3">
        @forelse ($this->technicianTasks as $task)
            <a href="{{ $task['url'] }}" class="block rounded-xl border-2 border-zinc-700 bg-zinc-900 p-4 transition active:scale-95 active:bg-zinc-800">
                {{-- Status badge no canto superior --}}
                <div class="mb-3 flex items-start justify-between">
                    <div class="flex items-center gap-2">
                        {{-- Ícone de tipo --}}
                        <span class="text-2xl">
                            @if ($task['type'] === 'maintenance')
                                🔧
                            @elseif ($task['type'] === 'mobilization')
                                🚚
                            @else
                                🔄
                            @endif
                        </span>
                        {{-- Badge de status --}}
                        @php
                            $statusBadge = match($task['status']) {
                                'Concluída' => ['EM CONCLUSÃO', 'bg-emerald-600/20 text-emerald-300'],
                                'Em Andamento' => ['EM ANDAMENTO', 'bg-yellow-600/20 text-yellow-300'],
                                'Agendada' => ['AGENDADA', 'bg-blue-600/20 text-blue-300'],
                                'synced' => ['SINCRONIZADO', 'bg-emerald-600/20 text-emerald-300'],
                                'pending' => ['PENDENTE', 'bg-amber-600/20 text-amber-300'],
                                default => ['NOVO', 'bg-zinc-700 text-zinc-300'],
                            };
                        @endphp
                        <div class="rounded-lg {{ $statusBadge[1] }} px-2 py-1 text-xs font-bold">
                            {{ $statusBadge[0] }}
                        </div>
                    </div>
                    {{-- Criticidade à direita --}}
                    @php
                        $critColor = match($task['criticality']) {
                            'A' => 'bg-red-600 text-white',
                            'B' => 'bg-yellow-600 text-zinc-950',
                            'C' => 'bg-green-600 text-white',
                            default => 'bg-zinc-700 text-zinc-300',
                        };
                    @endphp
                    <div class="rounded-full {{ $critColor }} h-10 w-10 flex items-center justify-center text-lg font-black">
                        {{ $task['criticality'] }}
                    </div>
                </div>

                {{-- Título principal (nome do ativo) --}}
                <h3 class="text-lg font-bold text-white line-clamp-2">
                    {{ $task['asset_name'] }}
                </h3>

                {{-- Patrimônio --}}
                <p class="mt-1 text-sm font-semibold text-zinc-300">
                    # {{ $task['patrimonio'] }}
                </p>

                {{-- Detalhes (Cliente + Contrato + CEP se externo) --}}
                @if ($task['client'])
                    <p class="mt-2 text-xs text-zinc-400">
                        📍 {{ $task['client'] }} | CEP: {{ $task['cep'] ?? '—' }}
                    </p>
                @endif

                {{-- SLA em destaque se expirado --}}
                @if ($task['sla_target_minutes'])
                    @php
                        $minutesElapsed = $task['created_at']->diffInMinutes(now());
                        $minutesRemaining = $task['sla_target_minutes'] - $minutesElapsed;
                    @endphp
                    @if ($minutesRemaining <= 0)
                        <div class="mt-3 rounded-lg bg-red-600/30 px-3 py-2 text-sm font-bold text-red-300">
                            ⚠️ SLA EXPIRADO
                        </div>
                    @elseif ($minutesRemaining <= 60)
                        <div class="mt-3 rounded-lg bg-yellow-600/30 px-3 py-2 text-sm font-bold text-yellow-300">
                            ⏱ {{ floor($minutesRemaining) }}m restantes
                        </div>
                    @endif
                @endif
            </a>
        @empty
            <div class="flex h-96 items-center justify-center">
                <div class="text-center">
                    <p class="text-2xl">📭</p>
                    <p class="mt-2 text-sm font-semibold text-zinc-400">Nenhuma tarefa para hoje</p>
                    <p class="mt-1 text-xs text-zinc-600">Volte mais tarde ou sincronize.</p>
                </div>
            </div>
        @endforelse
    </main>

    {{-- Rodapé fixo: ações --}}
    <footer class="sticky bottom-0 border-t-2 border-zinc-700 bg-gradient-to-t from-zinc-900 to-zinc-950 px-3 pb-4 pt-3 space-y-2">
        {{-- Botão primário: Iniciar próxima tarefa --}}
        @if ($this->pendingCount > 0)
            <a href="{{ $this->technicianTasks->first()['url'] ?? '#' }}"
               class="block w-full rounded-xl bg-gradient-to-r from-emerald-600 to-emerald-500 px-4 py-4 text-center text-lg font-black text-zinc-950 transition active:scale-95 hover:from-emerald-500 hover:to-emerald-400">
                ▶️ INICIAR PRÓXIMA TAREFA
            </a>
        @endif

        {{-- Botão secundário: Sincronizar --}}
        <button wire:click="$dispatch('refresh-technician-tasks')"
                wire:loading.attr="disabled"
                class="w-full rounded-lg border-2 border-zinc-700 bg-zinc-800 px-4 py-3 text-sm font-bold text-zinc-300 transition active:scale-95 hover:bg-zinc-700 disabled:opacity-50">
            🔄 Sincronizar
        </button>
    </footer>
</div>
