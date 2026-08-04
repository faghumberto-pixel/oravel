{{--
    "Minhas Ordens de Serviço" — lista do dia do técnico.
    Design native app mobile.
--}}

<div class="fixed inset-0 mx-auto flex max-w-md flex-col bg-slate-950">
    {{-- Header minimalista --}}
    <header class="sticky top-0 z-40 border-b border-slate-800 bg-slate-900/95 backdrop-blur px-5 py-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-black text-white">Tarefas</h1>
                <p class="text-sm text-slate-400 mt-1">{{ $this->pendingCount }} pendentes</p>
            </div>
            <div class="flex h-14 w-14 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600 text-lg font-bold text-white shadow-lg">
                {{ strtoupper(mb_substr(Auth::user()?->name ?? '?', 0, 1)) }}
            </div>
        </div>
    </header>

    {{-- Abertas / Encerradas --}}
    <div class="sticky top-16 z-30 flex border-b border-slate-800 bg-slate-900/95">
        <button wire:click="$set('activeTab', 'aberta')"
                class="flex-1 border-b-2 py-3 text-sm font-bold transition {{ $activeTab === 'aberta' ? 'border-emerald-500 text-white' : 'border-transparent text-slate-500' }}">
            Abertas ({{ $this->pendingCount }})
        </button>
        <button wire:click="$set('activeTab', 'encerrada')"
                class="flex-1 border-b-2 py-3 text-sm font-bold transition {{ $activeTab === 'encerrada' ? 'border-emerald-500 text-white' : 'border-transparent text-slate-500' }}">
            Encerradas
        </button>
    </div>

    {{-- Tabs de filtro (simples e limpo) -- so fazem sentido pra lista de abertas --}}
    @if ($activeTab === 'aberta')
        <div class="sticky top-[6.5rem] z-30 border-b border-slate-800 bg-slate-900/95 px-4 py-2 overflow-x-auto flex gap-2">
            <button wire:click="$set('filterType', '')"
                    :class="$filterType === '' ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-slate-400'"
                    class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold transition active:scale-95">
                Todos
            </button>
            <button wire:click="$set('filterCriticality', 'A')"
                    :class="$filterCriticality === 'A' ? 'bg-red-600 text-white' : 'bg-slate-800 text-slate-400'"
                    class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold transition active:scale-95">
                🔴 Urgente
            </button>
            <button wire:click="$set('filterCriticality', 'B')"
                    :class="$filterCriticality === 'B' ? 'bg-amber-600 text-white' : 'bg-slate-800 text-slate-400'"
                    class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold transition active:scale-95">
                🟡 Média
            </button>
            <button wire:click="$set('filterNature', 'external')"
                    :class="$filterNature === 'external' ? 'bg-purple-600 text-white' : 'bg-slate-800 text-slate-400'"
                    class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold transition active:scale-95">
                🌐 Cliente
            </button>
        </div>
    @endif

    {{-- Lista com scroll --}}
    <main class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
        @if ($activeTab === 'encerrada')
            @forelse ($this->closedTasks as $task)
                <a href="{{ $task['url'] }}" class="block rounded-2xl bg-slate-800 border border-slate-700 overflow-hidden transition hover:shadow-lg active:scale-95">
                    <div class="p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 rounded-lg bg-slate-700 flex items-center justify-center text-lg">⚙️</div>
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase">{{ $task['label'] }}</p>
                                    <p class="text-xs text-emerald-400">Concluída em {{ $task['closed_at']->format('d/m/Y H:i') }}</p>
                                </div>
                            </div>
                        </div>

                        <h3 class="text-base font-bold text-white">{{ $task['asset_name'] }}</h3>

                        <div class="space-y-1">
                            <p class="text-sm font-semibold text-slate-300"># {{ $task['patrimonio'] }}</p>
                            @if ($task['client'])
                                <p class="text-xs text-slate-400">📍 {{ $task['client'] }}</p>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <div class="flex h-96 items-center justify-center">
                    <div class="text-center">
                        <p class="text-4xl mb-2">📭</p>
                        <p class="text-base font-bold text-white">Nenhuma O.S. encerrada</p>
                    </div>
                </div>
            @endforelse
            <div class="h-20"></div>
        @else
        @forelse ($this->technicianTasks as $task)
            <a href="{{ $task['url'] }}" class="block rounded-2xl bg-slate-800 border border-slate-700 overflow-hidden transition hover:shadow-lg active:scale-95">
                <div class="p-4 space-y-3">
                    {{-- Tipo + Status --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-lg bg-slate-700 flex items-center justify-center text-lg">
                                @if ($task['type'] === 'maintenance')
                                    ⚙️
                                @elseif ($task['type'] === 'mobilization')
                                    📦
                                @else
                                    🔄
                                @endif
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-400 uppercase">{{ $task['label'] }}</p>
                                @php
                                    $statusLabel = match($task['status']) {
                                        'Concluída' => 'Concluída',
                                        'Em Andamento' => 'Em Andamento',
                                        'synced' => 'Sincronizada',
                                        'pending' => 'Pendente',
                                        default => ucfirst($task['status']),
                                    };
                                @endphp
                                <p class="text-xs text-slate-600 dark:text-slate-300">{{ $statusLabel }}</p>
                            </div>
                        </div>
                        {{-- Prioridade --}}
                        @php
                            $critColor = match($task['criticality']) {
                                'A' => 'bg-red-500 text-white',
                                'B' => 'bg-amber-500 text-white',
                                'C' => 'bg-green-500 text-white',
                                default => 'bg-slate-400 text-white',
                            };
                        @endphp
                        <div class="rounded-full {{ $critColor }} h-9 w-9 flex items-center justify-center text-xs font-black">
                            {{ $task['criticality'] }}
                        </div>
                    </div>

                    {{-- Nome do ativo --}}
                    <h3 class="text-base font-bold text-white">
                        {{ $task['asset_name'] }}
                    </h3>

                    {{-- Patrimônio + Cliente --}}
                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-slate-300">
                            # {{ $task['patrimonio'] }}
                        </p>
                        @if ($task['client'])
                            <p class="text-xs text-slate-400">
                                📍 {{ $task['client'] }}
                            </p>
                        @endif
                    </div>

                    {{-- SLA Alert --}}
                    @if ($task['sla_target_minutes'])
                        @php
                            $minutesElapsed = $task['created_at']->diffInMinutes(now());
                            $minutesRemaining = $task['sla_target_minutes'] - $minutesElapsed;
                        @endphp
                        @if ($minutesRemaining <= 0)
                            <div class="rounded-lg bg-red-950/30 px-3 py-2 text-sm font-bold text-red-400">
                                ⚠️ SLA EXPIRADO
                            </div>
                        @elseif ($minutesRemaining <= 60)
                            <div class="rounded-lg bg-amber-950/30 px-3 py-2 text-sm font-bold text-amber-300">
                                ⏱ {{ floor($minutesRemaining) }}m restantes
                            </div>
                        @endif
                    @endif
                </div>
            </a>
        @empty
            <div class="flex h-96 items-center justify-center">
                <div class="text-center">
                    <p class="text-4xl mb-2">📭</p>
                    <p class="text-base font-bold text-white">Nenhuma tarefa</p>
                    <p class="text-xs text-slate-400 mt-1">Volte mais tarde ou sincronize</p>
                </div>
            </div>
        @endforelse
        <div class="h-20"></div>
        @endif
    </main>

    {{-- Botões fixos no fundo -- so na aba de abertas, nao faz sentido pra encerradas --}}
    <footer class="sticky bottom-0 border-t border-slate-800 bg-white dark:bg-slate-900/95 backdrop-blur px-4 py-3 space-y-2">
        @if ($activeTab === 'aberta' && $this->pendingCount > 0)
            <a href="{{ $this->technicianTasks->first()['url'] ?? '#' }}"
               class="block w-full rounded-xl bg-gradient-to-r from-emerald-500 to-emerald-600 text-white px-4 py-3 text-center font-bold text-base transition active:scale-95 hover:shadow-lg shadow-lg">
                Iniciar Próxima Tarefa
            </a>
        @endif
        <button wire:click="$dispatch('refresh-technician-tasks')"
                class="w-full rounded-lg bg-slate-100 dark:bg-slate-800 text-white px-4 py-2.5 text-sm font-semibold transition active:scale-95">
            Sincronizar
        </button>
    </footer>
</div>
