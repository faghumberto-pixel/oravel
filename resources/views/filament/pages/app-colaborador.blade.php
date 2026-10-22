<x-mobile.shell :title="auth()->user()?->name" subtitle="App do Colaborador" active="inicio">
    @if ($this->podeVerOrdens())
        <x-mobile.menu-item :href="route('filament.admin.pages.technician-daily-tasks')" icon="📋" tone="emerald"
            title="Minhas Ordens de Serviço" description="Manutenções, mobilizações e desmobilizações do dia" />
    @endif

    <x-mobile.menu-item :href="route('chat.index')" icon="💬" tone="violet"
        title="Chat Corporativo" description="Fale com a equipe e o RH" />

    @if ($this->podeVerPonto())
        <x-mobile.menu-item :href="route('time-clock.offline')" icon="⏱️" tone="emerald"
            title="Bater Ponto" description="Entrada, pausa, almoço e saída" />

        <x-mobile.menu-item :href="route('filament.admin.pages.minhas-horas')" icon="📊" tone="sky"
            title="Minhas Horas" description="Horas trabalhadas e horas extras" />
    @endif

    @if ($this->podeVerFaltas())
        <x-mobile.menu-item :href="route('filament.admin.pages.minhas-faltas')" icon="🗓️" tone="amber"
            title="Minhas Faltas" description="Registrar ausência e anexar atestado" />
    @endif

    <x-mobile.menu-item :href="route('hour-meter.offline')" icon="🔧" tone="blue"
        title="Registrar Horímetro" description="Leitura de horas do equipamento" />

    <form method="POST" action="{{ route('filament.admin.auth.logout') }}" class="pt-2">
        @csrf
        <button type="submit" class="w-full rounded-xl border border-zinc-200 bg-white p-4 text-left text-sm font-bold text-rose-600 shadow-sm active:bg-zinc-50">
            🚪 Sair
        </button>
    </form>
</x-mobile.shell>
