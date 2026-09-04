{{--
    "Minhas Ordens de Serviço" — lista do dia do técnico.
    Design native app mobile.
--}}

{{-- absolute (nao fixed) -- em telas largas o pai (layouts.checklist-mobile)
     e' a moldura de celular centralizada, entao esta tela deve preencher
     SO' essa moldura, nao a viewport inteira (fixed ignoraria o md:rounded/
     md:overflow-hidden do pai e vazaria por cima). --}}
<div class="absolute inset-0 mx-auto flex max-w-md flex-col bg-slate-950" x-data="{ menuOpen: false }">
    {{-- Header minimalista --}}
    <header class="sticky top-0 z-40 border-b border-slate-800 bg-slate-900/95 backdrop-blur px-5 py-4">
        <div class="flex items-center justify-between gap-3">
            <button
                type="button"
                @click="menuOpen = true"
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-700 text-slate-300 active:bg-slate-800"
                aria-label="Abrir menu"
            >
                ☰
            </button>
            <div class="flex-1">
                <h1 class="text-3xl font-black text-white">Tarefas</h1>
                <p class="text-sm text-slate-400 mt-1">{{ $this->pendingCount }} pendentes</p>
            </div>
            <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600 text-lg font-bold text-white shadow-lg">
                {{ strtoupper(mb_substr(Auth::user()?->name ?? '?', 0, 1)) }}
            </div>
        </div>
    </header>

    {{-- Drawer de menu -- atalhos do tecnico, nao o sidebar completo do
         Filament (tem itens de admin que nao fazem sentido aqui). Layout
         checklist-mobile (compartilhado com checklist/wizard/dossie) e'
         minimalista de proposito, entao o menu fica so nesta tela. --}}
    <div
        x-show="menuOpen"
        x-cloak
        @click="menuOpen = false"
        class="fixed inset-0 z-50 bg-black/60"
    ></div>
    <aside
        x-show="menuOpen"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col bg-slate-900 border-r border-slate-800 px-4 py-6"
    >
        <div class="mb-6 flex items-center gap-3 px-2">
            <div class="flex h-11 w-11 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600 text-base font-bold text-white">
                {{ strtoupper(mb_substr(Auth::user()?->name ?? '?', 0, 1)) }}
            </div>
            <div class="min-w-0">
                <p class="truncate text-sm font-semibold text-white">{{ Auth::user()?->name }}</p>
                <p class="truncate text-xs text-slate-500">{{ Auth::user()?->email }}</p>
            </div>
        </div>

        <nav class="flex-1 space-y-1">
            <a href="{{ route('filament.admin.pages.technician-daily-tasks') }}"
               class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold text-white bg-slate-800">
                📋 Minhas Ordens de Serviço
            </a>
            <a href="{{ route('hour-meter.offline') }}"
               class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold text-slate-300 active:bg-slate-800">
                🕐 Registrar Horímetro
            </a>
            <a href="{{ route('filament.admin.pages.my-profile') }}"
               class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold text-slate-300 active:bg-slate-800">
                👤 Meu Perfil
            </a>
            <button onclick="window.location.href = '/admin'; return false;"
               class="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold text-blue-400 active:bg-slate-800 w-full text-left">
                🏠 Dashboard
            </button>
        </nav>

        <form method="POST" action="{{ route('filament.admin.auth.logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-sm font-semibold text-red-400 active:bg-slate-800">
                🚪 Sair
            </button>
        </form>
    </aside>

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
            {{-- Nao clicavel de proposito: uma vez "Concluída", o tecnico
                 perde acesso de edicao da propria O.S. (MaintenanceOrderPolicy::update()
                 -- so admin/supervisor do departamento a partir daqui,
                 pedido explicito do usuario 2026-08-04). Abrir o wizard
                 aqui so daria 403. --}}
            @forelse ($this->closedTasks as $task)
                <div class="rounded-2xl bg-slate-800 border border-slate-700 overflow-hidden opacity-90">
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
                </div>
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
        {{-- Alocações do Gantt aguardando confirmação -- pedido do
             usuário 2026-08-28. Técnico vê aqui e confirma sem sair da
             tela que já usa no dia a dia. --}}
        @if ($this->pendingAllocations->isNotEmpty())
            <div class="space-y-2">
                <p class="text-xs font-bold uppercase tracking-wide text-amber-400">
                    ⏳ Aguardando sua confirmação
                </p>
                @foreach ($this->pendingAllocations as $allocation)
                    <div class="rounded-2xl bg-amber-950/30 border border-amber-800/50 overflow-hidden p-4 space-y-2">
                        <p class="text-sm font-bold text-white">{{ $allocation->displayLabel() }}</p>
                        <p class="text-xs text-amber-300">{{ $allocation->starts_at->format('d/m/Y H:i') }}</p>
                        <button
                            wire:click="confirmAllocation('{{ $allocation->id }}')"
                            wire:key="confirm-{{ $allocation->id }}"
                            class="w-full rounded-xl bg-amber-500 text-slate-950 px-4 py-2.5 text-sm font-bold transition active:scale-95"
                        >
                            Confirmar
                        </button>
                    </div>
                @endforeach
            </div>
        @endif

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
