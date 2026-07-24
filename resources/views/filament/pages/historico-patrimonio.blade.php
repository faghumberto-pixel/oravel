<x-filament-panels::page>
    @if (! $asset)
        <x-filament::section>
            <form wire:submit="search" class="flex items-end gap-x-3">
                <div class="flex-1">
                    <label for="query" class="text-sm font-medium text-gray-950 dark:text-white">Patrimônio, nome, tag ou nº de série</label>
                    <input
                        type="text"
                        wire:model="query"
                        id="query"
                        placeholder="Ex: PAT-0001, Guindaste, AST-123..."
                        class="fi-input mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                    />
                    @error('query')
                        <span class="text-sm text-danger-600">{{ $message }}</span>
                    @enderror
                </div>
                <x-filament::button type="submit">
                    Buscar
                </x-filament::button>
            </form>
            <p class="mt-2 text-sm text-gray-500">Não precisa ser exato — aceita parte do texto.</p>

            @if (! empty($searchResults))
                <div class="mt-4 space-y-1">
                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ count($searchResults) }} ativos encontrados — escolha um:</p>
                    @foreach ($searchResults as $result)
                        <button
                            type="button"
                            wire:click="selectResult('{{ $result['id'] }}')"
                            class="flex w-full items-center justify-between rounded-lg border border-gray-200 px-3 py-2 text-left text-sm hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800"
                        >
                            <span class="font-medium text-gray-950 dark:text-white">{{ $result['name'] }}</span>
                            <span class="text-xs text-gray-400">Patrimônio: {{ $result['patrimonio'] ?? '—' }} · Tag: {{ $result['tag'] ?? '—' }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    @else
        @php
            $kpis = $this->getKpis();
            $series = $this->getEvolutionSeries();
            $breakdown = $this->getTypeBreakdown();
            $events = $this->getFilteredEvents();
            $maxSeries = max(1, collect($series)->max('total'));

            $tones = [
                'criticidade' => ['text' => 'text-rose-600 dark:text-rose-400', 'chip' => 'bg-rose-50 dark:bg-rose-500/10', 'bar' => 'bg-rose-500', 'border' => 'border-rose-500'],
                'problemas_reportados' => ['text' => 'text-amber-600 dark:text-amber-400', 'chip' => 'bg-amber-50 dark:bg-amber-500/10', 'bar' => 'bg-amber-500', 'border' => 'border-amber-500'],
                'pendencias' => ['text' => 'text-orange-600 dark:text-orange-400', 'chip' => 'bg-orange-50 dark:bg-orange-500/10', 'bar' => 'bg-orange-500', 'border' => 'border-orange-500'],
                'trocas' => ['text' => 'text-violet-600 dark:text-violet-400', 'chip' => 'bg-violet-50 dark:bg-violet-500/10', 'bar' => 'bg-violet-500', 'border' => 'border-violet-500'],
                'preventivas' => ['text' => 'text-emerald-600 dark:text-emerald-400', 'chip' => 'bg-emerald-50 dark:bg-emerald-500/10', 'bar' => 'bg-emerald-500', 'border' => 'border-emerald-500'],
                'corretivas' => ['text' => 'text-sky-600 dark:text-sky-400', 'chip' => 'bg-sky-50 dark:bg-sky-500/10', 'bar' => 'bg-sky-500', 'border' => 'border-sky-500'],
                'ordens_de_servico' => ['text' => 'text-indigo-600 dark:text-indigo-400', 'chip' => 'bg-indigo-50 dark:bg-indigo-500/10', 'bar' => 'bg-indigo-500', 'border' => 'border-indigo-500'],
            ];
            $neutral = ['text' => 'text-gray-600 dark:text-gray-400', 'chip' => 'bg-gray-100 dark:bg-gray-800', 'bar' => 'bg-gray-400', 'border' => 'border-gray-400'];
        @endphp

        {{-- ===================== CABEÇALHO ===================== --}}
        <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
            <div>
                <h2 class="text-xl font-bold text-gray-950 dark:text-white">{{ $asset->name }}</h2>
                <p class="text-sm text-gray-500">
                    Patrimônio: {{ $asset->patrimonio ?? '—' }} · Tag: {{ $asset->tag ?? '—' }}
                    @if($asset->abcMatrix)
                        · Nível ABC atual: <span class="font-semibold">{{ $asset->abcMatrix->nivel }}</span>
                    @endif
                </p>
            </div>
            <div class="flex gap-x-2">
                <x-filament::button
                    tag="a"
                    :href="\App\Filament\Resources\AssetResource::getUrl('edit', ['record' => $asset])"
                    icon="heroicon-o-pencil-square"
                    color="gray"
                >
                    Editar Ativo
                </x-filament::button>
                <x-filament::button wire:click="clear" color="gray" icon="heroicon-o-magnifying-glass">
                    Nova busca
                </x-filament::button>
            </div>
        </div>

        {{-- ===================== FILTROS ===================== --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4 mb-4">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-[10.5px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">De</label>
                    <input type="date" wire:model.live="dateFrom" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                </div>
                <div>
                    <label class="block text-[10.5px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">Até</label>
                    <input type="date" wire:model.live="dateTo" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                </div>
                <div>
                    <label class="block text-[10.5px] font-bold uppercase tracking-wide text-gray-400 dark:text-gray-500 mb-1">Tipo de Evento</label>
                    <select wire:model.live="tipo" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                        @foreach($this->tipoOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- ===================== KPIs ===================== --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 mb-4">
            @foreach([
                ['value' => $kpis['total'], 'label' => 'Total de Eventos', 'icon' => 'heroicon-o-list-bullet', 'tone' => $neutral],
                ['value' => $kpis['criticidade'], 'label' => 'Criticidade', 'icon' => 'heroicon-o-fire', 'tone' => $tones['criticidade']],
                ['value' => $kpis['pendencias'], 'label' => 'Pendências', 'icon' => 'heroicon-o-exclamation-circle', 'tone' => $tones['pendencias']],
                ['value' => $kpis['osTrocas'], 'label' => 'OS / Trocas', 'icon' => 'heroicon-o-wrench', 'tone' => $tones['ordens_de_servico']],
                ['value' => $kpis['problemas'], 'label' => 'Problemas Reportados', 'icon' => 'heroicon-o-exclamation-triangle', 'tone' => $tones['problemas_reportados']],
            ] as $kpi)
                <div class="flex items-center gap-2.5 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $kpi['tone']['chip'] }} {{ $kpi['tone']['text'] }}">
                        <x-dynamic-component :component="$kpi['icon']" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 leading-tight">
                        <div class="text-xl font-bold tabular-nums text-gray-900 dark:text-white">{{ $kpi['value'] }}</div>
                        <div class="text-[10px] font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500 truncate">{{ $kpi['label'] }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ===================== GRÁFICOS ===================== --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <div class="lg:col-span-2 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                <h3 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Eventos por Mês</h3>
                <div class="flex items-end gap-2" style="height: 140px">
                    @forelse($series as $point)
                        <div class="flex-1 flex flex-col items-center justify-end h-full">
                            <span class="text-[10px] font-mono text-gray-400 dark:text-gray-500 mb-1">{{ $point['total'] }}</span>
                            <div class="w-full max-w-[28px] rounded-t bg-indigo-500" style="height: {{ max(2, round($point['total'] / $maxSeries * 100)) }}%"></div>
                            <span class="text-[9.5px] text-gray-400 dark:text-gray-500 mt-1.5">{{ $point['label'] }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic">Sem eventos no período.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
                <h3 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Proporção por Tipo</h3>
                <div class="space-y-2.5">
                    @forelse($breakdown as $item)
                        @php $tone = $tones[$item['tipo']] ?? $neutral; @endphp
                        <div>
                            <div class="flex items-center justify-between text-[11px] mb-1">
                                <span class="font-semibold text-gray-700 dark:text-gray-300">{{ $item['label'] }}</span>
                                <span class="text-gray-400 dark:text-gray-500 tabular-nums">{{ $item['count'] }} ({{ $item['pct'] }}%)</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                                <div class="h-full rounded-full {{ $tone['bar'] }}" style="width: {{ $item['pct'] }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 italic">Sem eventos no período.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ===================== LISTA DE EVENTOS ===================== --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-4">
            <h3 class="text-[11px] font-bold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">
                Eventos ({{ $events->count() }})
            </h3>

            @if($events->isEmpty())
                <div class="text-center py-12">
                    <x-heroicon-o-inbox class="w-8 h-8 mx-auto text-gray-300 dark:text-gray-700 mb-2" />
                    <p class="text-sm font-bold text-gray-500 dark:text-gray-400">Nenhum evento nesse filtro.</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach($events as $event)
                        @php
                            $primaryTipo = collect(['criticidade', 'problemas_reportados', 'pendencias', 'trocas', 'preventivas', 'corretivas', 'ordens_de_servico'])
                                ->first(fn ($t) => in_array($t, $event['tipos'], true));
                            $tone = $tones[$primaryTipo] ?? $neutral;
                        @endphp
                        <div class="flex gap-3 rounded-lg border-l-4 {{ $tone['border'] }} border-t border-r border-b border-gray-200 dark:border-gray-700 p-3">
                            <div class="w-20 shrink-0 text-[11px] font-mono text-gray-400 dark:text-gray-500 pt-0.5">
                                {{ $event['at']->format('d/m/Y') }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-bold text-gray-900 dark:text-gray-100">{{ $event['title'] }}</p>
                                @if(!empty($event['body']))
                                    <p class="text-[11.5px] text-gray-500 dark:text-gray-400 mt-0.5">{{ $event['body'] }}</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <p class="text-[11px] text-gray-400 dark:text-gray-600 italic text-center pt-3">
            "Criticidade" reúne mudanças reais de nível ABC (a partir de 24/07/2026, quando o histórico passou a ser
            gravado) e avarias de severidade grave registradas no período.
        </p>
    @endif
</x-filament-panels::page>
