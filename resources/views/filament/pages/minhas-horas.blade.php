<x-mobile.shell title="Minhas Horas" subtitle="Últimos 14 dias, a partir das batidas de ponto" :back="route('filament.admin.pages.app-colaborador')">
    @forelse ($this->days as $day)
        <div class="rounded-xl border border-zinc-200 bg-white p-4 shadow-sm" x-data="{ open: false }">
            <button type="button" @click="open = !open" class="flex w-full items-center justify-between text-left">
                <div>
                    <p class="text-sm font-bold text-zinc-900">{{ $day['date']->translatedFormat('D, d/m') }}</p>
                    <p class="text-xs text-zinc-600">{{ $day['date']->isToday() ? 'Hoje' : $day['date']->diffForHumans() }}</p>
                </div>
                <div class="text-right">
                    <p class="text-lg font-black {{ $day['worked_minutes'] > 0 ? 'text-emerald-700' : 'text-zinc-400' }}">
                        {{ $this->formatMinutes($day['worked_minutes']) }}
                    </p>
                    @if ($day['em_andamento'])
                        <p class="text-[10px] font-bold text-amber-700">EM ANDAMENTO</p>
                    @elseif ($day['overtime_minutes'] > 0)
                        <p class="text-[10px] font-bold text-orange-700">+{{ $this->formatMinutes($day['overtime_minutes']) }} EXTRA</p>
                    @endif
                </div>
            </button>

            <div x-show="open" x-cloak class="mt-3 space-y-1 border-t border-zinc-200 pt-3">
                @forelse ($day['events'] as $event)
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-zinc-600">{{ \App\Models\TimeClock::tipoLabels()[$event->tipo] ?? $event->tipo }}</span>
                        <span class="font-mono text-zinc-800">{{ $event->recorded_at->format('H:i') }}</span>
                    </div>
                @empty
                    <p class="text-xs text-zinc-600">Nenhuma batida neste dia.</p>
                @endforelse
            </div>
        </div>
    @empty
        <div class="rounded-xl border border-zinc-200 bg-white p-6 text-center shadow-sm">
            <p class="text-sm text-zinc-600">Nenhum registro de ponto encontrado.</p>
        </div>
    @endforelse
</x-mobile.shell>
