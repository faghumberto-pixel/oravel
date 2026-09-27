<div class="absolute inset-0 mx-auto flex max-w-md flex-col overflow-y-auto bg-slate-950">
    <header class="sticky top-0 z-10 flex items-center gap-3 border-b border-slate-800 bg-slate-900/95 px-5 py-4 backdrop-blur">
        <a href="{{ route('filament.admin.pages.technician-daily-tasks') }}" class="text-slate-400 text-lg leading-none">←</a>
        <h1 class="text-lg font-black text-white">Minhas Horas</h1>
    </header>

    <main class="flex-1 space-y-3 px-5 py-4">
        <p class="text-xs text-slate-500">Últimos 14 dias, calculado a partir das batidas de ponto.</p>

        @forelse ($this->days as $day)
            <div class="rounded-2xl bg-slate-900 p-4" x-data="{ open: false }">
                <button type="button" @click="open = !open" class="flex w-full items-center justify-between text-left">
                    <div>
                        <p class="text-sm font-bold text-white">{{ $day['date']->translatedFormat('D, d/m') }}</p>
                        <p class="text-[11px] text-slate-500">{{ $day['date']->isToday() ? 'Hoje' : $day['date']->diffForHumans() }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-black {{ $day['worked_minutes'] > 0 ? 'text-emerald-400' : 'text-slate-600' }}">
                            {{ $this->formatMinutes($day['worked_minutes']) }}
                        </p>
                        @if ($day['em_andamento'])
                            <p class="text-[10px] font-bold text-amber-400">EM ANDAMENTO</p>
                        @elseif ($day['overtime_minutes'] > 0)
                            <p class="text-[10px] font-bold text-orange-400">+{{ $this->formatMinutes($day['overtime_minutes']) }} EXTRA</p>
                        @endif
                    </div>
                </button>

                <div x-show="open" x-cloak class="mt-3 space-y-1 border-t border-slate-800 pt-3">
                    @forelse ($day['events'] as $event)
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400">{{ \App\Models\TimeClock::tipoLabels()[$event->tipo] ?? $event->tipo }}</span>
                            <span class="font-mono text-slate-300">{{ $event->recorded_at->format('H:i') }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-600">Nenhuma batida neste dia.</p>
                    @endforelse
                </div>
            </div>
        @empty
            <div class="rounded-2xl bg-slate-900 p-6 text-center">
                <p class="text-sm text-slate-500">Nenhum registro de ponto encontrado.</p>
            </div>
        @endforelse
    </main>
</div>
