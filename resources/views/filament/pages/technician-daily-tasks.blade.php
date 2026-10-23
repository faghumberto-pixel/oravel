{{--
    "Minhas Ordens de Serviço" — lista do dia do técnico.
    Usa o padrão visual mobile (x-mobile.shell): cabeçalho azul, abas, cartões e barra de ícones.
    Encerradas não são clicáveis de propósito: uma vez "Concluída", o técnico perde a edição da própria O.S.
    (MaintenanceOrderPolicy::update() -- só admin/supervisor do departamento, pedido do usuário 2026-08-04).
--}}
<x-mobile.shell title="Minhas OS" :subtitle="$this->pendingCount.' pendente(s)'">
    <x-slot:tabs>
        <div class="space-y-2">
            <div class="flex gap-2">
                <button wire:click="$set('activeTab', 'aberta')"
                        class="rounded-full px-4 py-2 font-semibold transition {{ $activeTab === 'aberta' ? 'bg-blue-600 text-white' : 'bg-zinc-200 text-zinc-700' }}">
                    Abertas <span class="text-sm font-normal">({{ $this->pendingCount }})</span>
                </button>
                <button wire:click="$set('activeTab', 'encerrada')"
                        class="rounded-full px-4 py-2 font-semibold transition {{ $activeTab === 'encerrada' ? 'bg-blue-600 text-white' : 'bg-zinc-200 text-zinc-700' }}">
                    Encerradas
                </button>
            </div>

            {{-- Filtros: só fazem sentido para a lista de abertas --}}
            @if ($activeTab === 'aberta')
                <div class="flex gap-2 overflow-x-auto pb-1">
                    <button wire:click="limparFiltros"
                            class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold transition active:scale-95 {{ $filterType === '' && $filterCriticality === '' && $filterNature === '' ? 'bg-blue-600 text-white' : 'bg-zinc-200 text-zinc-700' }}">
                        Todos
                    </button>
                    <button wire:click="$set('filterCriticality', '{{ $filterCriticality === 'A' ? '' : 'A' }}')"
                            class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold transition active:scale-95 {{ $filterCriticality === 'A' ? 'bg-red-600 text-white' : 'bg-zinc-200 text-zinc-700' }}">
                        🔴 Urgente
                    </button>
                    <button wire:click="$set('filterCriticality', '{{ $filterCriticality === 'B' ? '' : 'B' }}')"
                            class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold transition active:scale-95 {{ $filterCriticality === 'B' ? 'bg-amber-500 text-white' : 'bg-zinc-200 text-zinc-700' }}">
                        🟡 Média
                    </button>
                    <button wire:click="$set('filterNature', '{{ $filterNature === 'external' ? '' : 'external' }}')"
                            class="shrink-0 rounded-full px-4 py-1.5 text-sm font-semibold transition active:scale-95 {{ $filterNature === 'external' ? 'bg-purple-600 text-white' : 'bg-zinc-200 text-zinc-700' }}">
                        🌐 Cliente
                    </button>
                </div>
            @endif
        </div>
    </x-slot:tabs>

    @if ($activeTab === 'encerrada')
        @forelse ($this->closedTasks as $task)
            <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm">
                <div class="space-y-3 p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100 text-lg">⚙️</div>
                        <div>
                            <p class="text-xs font-bold uppercase text-zinc-600">{{ $task['label'] }}</p>
                            <p class="text-xs text-emerald-700">Concluída em {{ $task['closed_at']->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>

                    <h3 class="text-base font-bold text-zinc-900">{{ $task['asset_name'] }}</h3>

                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-zinc-700"># {{ $task['patrimonio'] }}</p>
                        @if ($task['client'])
                            <p class="text-xs text-zinc-600">📍 {{ $task['client'] }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="py-12 text-center">
                <p class="mb-2 text-4xl">📭</p>
                <p class="text-base font-bold text-zinc-900">Nenhuma O.S. encerrada</p>
            </div>
        @endforelse
    @else
        {{-- Alocações do Gantt aguardando confirmação (pedido do usuário 2026-08-28) --}}
        @if ($this->pendingAllocations->isNotEmpty())
            <div class="space-y-2">
                <p class="text-xs font-bold uppercase tracking-wide text-amber-700">⏳ Aguardando sua confirmação</p>
                @foreach ($this->pendingAllocations as $allocation)
                    <div class="space-y-2 overflow-hidden rounded-xl border border-amber-200 bg-amber-50 p-4">
                        <p class="text-sm font-bold text-zinc-900">{{ $allocation->displayLabel() }}</p>
                        <p class="text-xs text-amber-800">{{ $allocation->starts_at->format('d/m/Y H:i') }}</p>
                        <button
                            wire:click="confirmAllocation('{{ $allocation->id }}')"
                            wire:key="confirm-{{ $allocation->id }}"
                            class="w-full rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-white transition active:scale-95"
                        >
                            Confirmar
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

        @forelse ($this->technicianTasks as $task)
            <a href="{{ $task['url'] }}" class="block overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm transition hover:shadow-md active:scale-95">
                <div class="space-y-3 p-4">
                    {{-- Tipo + Status + Prioridade --}}
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100 text-lg">
                                @if ($task['type'] === 'maintenance')
                                    ⚙️
                                @elseif ($task['type'] === 'mobilization')
                                    📦
                                @else
                                    🔄
                                @endif
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase text-zinc-600">{{ $task['label'] }}</p>
                                @php
                                    $statusLabel = match($task['status']) {
                                        'Concluída' => 'Concluída',
                                        'Em Andamento' => 'Em Andamento',
                                        'synced' => 'Sincronizada',
                                        'pending' => 'Pendente',
                                        default => ucfirst($task['status']),
                                    };
                                    $critColor = match($task['criticality']) {
                                        'A' => 'bg-red-500 text-white',
                                        'B' => 'bg-amber-500 text-white',
                                        'C' => 'bg-green-600 text-white',
                                        default => 'bg-zinc-400 text-white',
                                    };
                                @endphp
                                <p class="text-xs text-zinc-700">{{ $statusLabel }}</p>
                            </div>
                        </div>
                        <div class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-black {{ $critColor }}">
                            {{ $task['criticality'] }}
                        </div>
                    </div>

                    <h3 class="text-base font-bold text-zinc-900">{{ $task['asset_name'] }}</h3>

                    <div class="space-y-1">
                        <p class="text-sm font-semibold text-zinc-700"># {{ $task['patrimonio'] }}</p>
                        @if ($task['client'])
                            <p class="text-xs text-zinc-600">📍 {{ $task['client'] }}</p>
                        @endif
                    </div>

                    {{-- SLA --}}
                    @if ($task['sla_target_minutes'])
                        @php
                            $minutesElapsed = (int) $task['created_at']->diffInMinutes(now());
                            $minutesRemaining = $task['sla_target_minutes'] - $minutesElapsed;
                        @endphp
                        @if ($minutesRemaining <= 0)
                            <div class="rounded-lg bg-red-50 px-3 py-2 text-sm font-bold text-red-700">⚠️ SLA EXPIRADO</div>
                        @elseif ($minutesRemaining <= 60)
                            <div class="rounded-lg bg-amber-50 px-3 py-2 text-sm font-bold text-amber-800">⏱ {{ floor($minutesRemaining) }}m restantes</div>
                        @endif
                    @endif
                </div>
            </a>
        @empty
            <div class="py-12 text-center">
                <p class="mb-2 text-4xl">📭</p>
                <p class="text-base font-bold text-zinc-900">Nenhuma tarefa</p>
                <p class="mt-1 text-xs text-zinc-600">Volte mais tarde ou sincronize</p>
            </div>
        @endforelse
    @endif

    <x-slot:bar>
        @if ($activeTab === 'aberta' && $this->pendingCount > 0)
            <a href="{{ $this->technicianTasks->first()['url'] ?? '#' }}"
               class="block w-full rounded-xl bg-blue-600 px-4 py-3 text-center text-base font-bold text-white shadow-lg transition hover:bg-blue-700 active:scale-95">
                Iniciar Próxima Tarefa
            </a>
        @endif
        <button wire:click="$dispatch('refresh-technician-tasks')"
                class="w-full rounded-lg bg-zinc-100 px-4 py-2.5 text-sm font-semibold text-zinc-700 transition active:scale-95">
            Sincronizar
        </button>
    </x-slot:bar>
</x-mobile.shell>
