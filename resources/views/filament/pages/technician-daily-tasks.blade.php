{{--
    "Minhas Ordens de Serviço" — lista do dia do técnico.
    Design polido para parecer um app mobile nativo.
--}}

<div class="fixed inset-0 mx-auto flex max-w-md flex-col bg-slate-950">
    {{-- Header polido --}}
    <header class="sticky top-0 z-40 border-b border-slate-800 bg-slate-900/95 backdrop-blur px-5 py-4">
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Suas Tarefas</p>
                    <h1 class="text-2xl font-black text-white mt-1">Ordens de Serviço</h1>
                </div>
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600 text-xs font-bold text-white shadow-lg">
                        {{ strtoupper(mb_substr(Auth::user()?->name ?? '?', 0, 1)) }}
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <div class="h-2 w-2 rounded-full bg-emerald-500"></div>
                <span class="font-semibold text-slate-300">{{ $this->pendingCount }} pendentes</span>
                <span class="text-slate-400">•</span>
                <span class="text-slate-400">{{ $this->completedCount }} completas</span>
            </div>
        </div>
    </header>

    {{-- Filtros estilo iOS --}}
    <div class="border-b border-slate-800 bg-slate-900/50 px-4 py-4 space-y-4 overflow-y-auto">
        {{-- Tipo de Tarefa --}}
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase mb-2">Tipo</p>
            <div class="flex gap-2">
                <button wire:click="$set('filterType', '')"
                        :class="$filterType === '' ? 'bg-emerald-500 text-white shadow-md' : 'bg-slate-800 text-slate-300'"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition active:scale-95">
                    Todos
                </button>
                <button wire:click="$set('filterType', 'maintenance')"
                        :class="$filterType === 'maintenance' ? 'bg-emerald-500 text-white shadow-md' : 'bg-slate-800 text-slate-300'"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition active:scale-95">
                    Manutenção
                </button>
                <button wire:click="$set('filterType', 'mobilization')"
                        :class="$filterType === 'mobilization' ? 'bg-emerald-500 text-white shadow-md' : 'bg-slate-800 text-slate-300'"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition active:scale-95">
                    Mob.
                </button>
                <button wire:click="$set('filterType', 'demobilization')"
                        :class="$filterType === 'demobilization' ? 'bg-emerald-500 text-white shadow-md' : 'bg-slate-800 text-slate-300'"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition active:scale-95">
                    Desm.
                </button>
            </div>
        </div>

        {{-- Prioridade --}}
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase mb-2">Prioridade</p>
            <div class="flex gap-2">
                <button wire:click="$set('filterCriticality', '')"
                        :class="$filterCriticality === '' ? 'bg-emerald-500 text-white shadow-md' : 'bg-slate-800 text-slate-300'"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition active:scale-95">
                    Todas
                </button>
                <button wire:click="$set('filterCriticality', 'A')"
                        :class="$filterCriticality === 'A' ? 'bg-red-500 text-white shadow-md' : 'bg-red-950 text-red-300'"
                        class="rounded-lg px-3 py-2 text-xs font-bold transition active:scale-95">
                    Alta
                </button>
                <button wire:click="$set('filterCriticality', 'B')"
                        :class="$filterCriticality === 'B' ? 'bg-amber-500 text-white shadow-md' : 'bg-amber-950 text-amber-300'"
                        class="rounded-lg px-3 py-2 text-xs font-bold transition active:scale-95">
                    Média
                </button>
                <button wire:click="$set('filterCriticality', 'C')"
                        :class="$filterCriticality === 'C' ? 'bg-green-500 text-white shadow-md' : 'bg-green-950 text-green-300'"
                        class="rounded-lg px-3 py-2 text-xs font-bold transition active:scale-95">
                    Baixa
                </button>
            </div>
        </div>

        {{-- Localização --}}
        <div>
            <p class="text-xs font-bold text-slate-400 uppercase mb-2">Local</p>
            <div class="flex gap-2">
                <button wire:click="$set('filterNature', '')"
                        :class="$filterNature === '' ? 'bg-emerald-500 text-white shadow-md' : 'bg-slate-800 text-slate-300'"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition active:scale-95">
                    Ambas
                </button>
                <button wire:click="$set('filterNature', 'internal')"
                        :class="$filterNature === 'internal' ? 'bg-blue-500 text-white shadow-md' : 'bg-blue-950 text-blue-300'"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition active:scale-95">
                    Interna
                </button>
                <button wire:click="$set('filterNature', 'external')"
                        :class="$filterNature === 'external' ? 'bg-purple-500 text-white shadow-md' : 'bg-purple-950 text-purple-300'"
                        class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition active:scale-95">
                    Externa
                </button>
            </div>
        </div>
    </div>

    {{-- Lista com scroll --}}
    <main class="flex-1 overflow-y-auto px-4 py-4 space-y-3">
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
    </main>

    {{-- Botões fixos no fundo --}}
    <footer class="sticky bottom-0 border-t border-slate-800 bg-white dark:bg-slate-900/95 backdrop-blur px-4 py-3 space-y-2">
        @if ($this->pendingCount > 0)
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
