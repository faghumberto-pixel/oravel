{{-- Agenda Técnica — próximos 30 dias. Padrão visual mobile (x-mobile.shell). --}}
<x-mobile.shell title="Programação" :subtitle="'Próximos 30 dias · '.count($this->agendaItems).' agendamentos'">
    <x-slot:search>
        <div class="relative">
            <input wire:model.live="search"
                   type="text"
                   placeholder="Buscar..."
                   class="w-full rounded-xl border border-zinc-200 bg-white px-4 py-3 pr-10 text-zinc-900 placeholder-zinc-400 shadow-md focus:border-transparent focus:outline-none focus:ring-2 focus:ring-blue-500">
            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400">🔍</span>
        </div>
    </x-slot:search>

    <x-slot:tabs>
        <div class="space-y-2">
            <div class="flex gap-2 overflow-x-auto pb-1">
                @foreach (['' => 'Todos', 'appointment' => 'Agendamentos', 'order' => 'O.S.'] as $valor => $rotulo)
                    <button wire:click="$set('filterType', '{{ $valor }}')"
                            class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold transition active:scale-95 {{ $filterType === $valor ? 'bg-blue-600 text-white' : 'bg-zinc-200 text-zinc-700' }}">
                        {{ $rotulo }}
                    </button>
                @endforeach
            </div>
            <div class="flex gap-2">
                @foreach (['date' => 'Por data', 'urgency' => 'Por urgência'] as $valor => $rotulo)
                    <button wire:click="$set('sortBy', '{{ $valor }}')"
                            class="flex-1 rounded-lg px-3 py-2 text-xs font-semibold transition active:scale-95 {{ $sortBy === $valor ? 'bg-zinc-800 text-white' : 'bg-zinc-200 text-zinc-700' }}">
                        {{ $rotulo }}
                    </button>
                @endforeach
            </div>
        </div>
    </x-slot:tabs>

    @forelse ($this->agendaItems as $item)
        <div @if($item['type'] === 'order') onclick="window.location.href='{{ $item['url'] }}'" @endif
             class="overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm @if($item['type'] === 'order') cursor-pointer transition hover:shadow-md active:scale-95 @endif">
            <div class="space-y-2 p-4">
                <div class="flex items-start justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100 text-lg">{{ $item['icon'] }}</div>
                        <div>
                            <p class="text-xs font-bold uppercase text-zinc-600">{{ $item['label'] }}</p>
                            <p class="text-xs text-zinc-700">{{ $item['date'] }} às {{ $item['time'] }}</p>
                        </div>
                    </div>
                    @if ($item['urgente'] ?? false)
                        <span class="inline-block rounded-full bg-red-500 px-2 py-1 text-[10px] font-bold text-white">URGENTE</span>
                    @endif
                </div>

                <h3 class="line-clamp-2 text-base font-bold text-zinc-900">{{ $item['title'] }}</h3>

                @if ($item['description'])
                    <p class="line-clamp-2 text-sm text-zinc-600">{{ $item['description'] }}</p>
                @endif

                <div class="space-y-1 text-xs">
                    @if ($item['type'] === 'order' && $item['patrimonio'])
                        <p class="text-zinc-600">Patrimônio: <span class="font-semibold text-zinc-900"># {{ $item['patrimonio'] }}</span></p>
                    @endif
                    @if ($item['client'] ?? false)
                        <p class="text-zinc-600">Local: <span class="font-semibold text-zinc-900">📍 {{ $item['client'] }}</span></p>
                    @endif
                </div>

                @if ($item['type'] === 'order')
                    <div class="border-t border-zinc-200 pt-2">
                        <span class="inline-block rounded-full px-3 py-1 text-xs font-semibold {{ $item['color'] }}">{{ $item['status'] }}</span>
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="py-12 text-center">
            <p class="mb-2 text-4xl">🎉</p>
            <p class="text-base font-bold text-zinc-900">Sem agendamentos</p>
            <p class="mt-1 text-xs text-zinc-600">Você está em dia nos próximos 30 dias</p>
        </div>
    @endforelse

    <x-slot:bar>
        <a href="{{ route('filament.admin.pages.agenda-tecnico') }}"
           class="block w-full rounded-xl bg-blue-600 px-4 py-3 text-center text-base font-bold text-white shadow-lg transition hover:bg-blue-700 active:scale-95">
            Ver calendário completo
        </a>
    </x-slot:bar>
</x-mobile.shell>
