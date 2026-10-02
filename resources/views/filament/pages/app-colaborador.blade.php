<div class="absolute inset-0 mx-auto flex max-w-md flex-col overflow-y-auto bg-slate-950">
    <header class="border-b border-slate-800 bg-slate-900/95 px-5 py-6 backdrop-blur">
        <div class="flex items-center gap-3">
            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gradient-to-br from-emerald-500 to-emerald-600 text-lg font-bold text-white">
                {{ strtoupper(mb_substr(auth()->user()?->name ?? '?', 0, 1)) }}
            </div>
            <div class="min-w-0">
                <p class="truncate text-base font-bold text-white">{{ auth()->user()?->name }}</p>
                <p class="text-xs text-slate-500">App do Colaborador</p>
            </div>
        </div>
    </header>

    <main class="flex-1 space-y-3 px-5 py-5">
        @if ($this->podeVerOrdens())
            <a href="{{ route('filament.admin.pages.technician-daily-tasks') }}" class="flex items-center gap-4 rounded-2xl bg-slate-900 p-4 active:bg-slate-800">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/15 text-2xl">📋</span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-white">Minhas Ordens de Serviço</p>
                    <p class="text-xs text-slate-500">Manutenções, mobilizações e desmobilizações do dia</p>
                </div>
            </a>
        @endif

        <a href="{{ route('chat.index') }}" class="flex items-center gap-4 rounded-2xl bg-slate-900 p-4 active:bg-slate-800">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-500/15 text-2xl">💬</span>
            <div class="min-w-0">
                <p class="text-sm font-bold text-white">Chat Corporativo</p>
                <p class="text-xs text-slate-500">Fale com a equipe e o RH</p>
            </div>
        </a>

        @if ($this->podeVerPonto())
            <a href="{{ route('time-clock.offline') }}" class="flex items-center gap-4 rounded-2xl bg-slate-900 p-4 active:bg-slate-800">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-500/15 text-2xl">⏱️</span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-white">Bater Ponto</p>
                    <p class="text-xs text-slate-500">Entrada, pausa, almoço e saída</p>
                </div>
            </a>

            <a href="{{ route('filament.admin.pages.minhas-horas') }}" class="flex items-center gap-4 rounded-2xl bg-slate-900 p-4 active:bg-slate-800">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-sky-500/15 text-2xl">📊</span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-white">Minhas Horas</p>
                    <p class="text-xs text-slate-500">Horas trabalhadas e horas extras</p>
                </div>
            </a>
        @endif

        @if ($this->podeVerFaltas())
            <a href="{{ route('filament.admin.pages.minhas-faltas') }}" class="flex items-center gap-4 rounded-2xl bg-slate-900 p-4 active:bg-slate-800">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-500/15 text-2xl">🗓️</span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-white">Minhas Faltas</p>
                    <p class="text-xs text-slate-500">Registrar ausência e anexar atestado</p>
                </div>
            </a>
        @endif

        <a href="{{ route('hour-meter.offline') }}" class="flex items-center gap-4 rounded-2xl bg-slate-900 p-4 active:bg-slate-800">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-blue-500/15 text-2xl">🔧</span>
            <div class="min-w-0">
                <p class="text-sm font-bold text-white">Registrar Horímetro</p>
                <p class="text-xs text-slate-500">Leitura de horas do equipamento</p>
            </div>
        </a>

        <form method="POST" action="{{ route('filament.admin.auth.logout') }}" class="pt-2">
            @csrf
            <button type="submit" class="w-full rounded-2xl bg-slate-900 p-4 text-left text-sm font-bold text-red-400 active:bg-slate-800">
                🚪 Sair
            </button>
        </form>
    </main>
</div>
