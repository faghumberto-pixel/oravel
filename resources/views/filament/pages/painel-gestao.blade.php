<x-filament-panels::page>
    @php
        $tenantName = \App\Support\Tenancy::current()?->name;
    @endphp

    {{-- Mesmo padrão do Dashboard PMP: tema escuro fixo nesta página (wrapper
         .dark escopado, não depende do toggle claro/escuro do painel) --
         inclusive pros widgets do Filament embutidos via @livewire, que já
         têm dark: bakeado por padrão. --}}
    <div class="dark">
    <div class="max-w-full flex flex-col gap-3 rounded-2xl bg-gray-900 p-3 text-gray-100 ring-1 ring-white/5">

        {{-- ===================== CABEÇALHO COMPACTO ===================== --}}
        <div class="flex items-center justify-between gap-3 rounded-xl bg-gray-800/60 backdrop-blur-sm px-4 py-2.5 ring-1 ring-white/5">
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-100 truncate">Painel de Controle</p>
                <p class="text-[10px] text-gray-400 truncate">{{ $tenantName ?? 'Visão Geral' }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="hidden sm:inline text-[10px] font-medium text-gray-500 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-[10px] font-semibold uppercase tracking-wide text-emerald-400">Ao vivo</span>
            </div>
        </div>

        {{-- ===================== ABAS (segmented control compacto) ===================== --}}
        <div class="inline-flex self-start gap-1 rounded-lg bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5 p-1">
            <button
                type="button"
                wire:click="selectTab('gestao')"
                class="px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-wide rounded-md transition {{ $activeTab === 'gestao' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-400 hover:text-gray-200' }}"
            >
                Painel de Gestão
            </button>
            <button
                type="button"
                wire:click="selectTab('comando')"
                class="px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-wide rounded-md transition {{ $activeTab === 'comando' ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-400 hover:text-gray-200' }}"
            >
                Centro de Comando
            </button>
        </div>

        {{-- ===================== ÁREA DE CONTEÚDO ===================== --}}
        <div class="w-full flex flex-col gap-3">
            @if($activeTab === 'gestao')
                {{-- PAINEL DE GESTAO -- widgets exclusivos do segmento do tenant
                     (Eventos/Construcao Civil/Industrial-Hospitalar/Generico),
                     ver App\Support\SegmentDashboardWidgets --}}
                @php
                    $gestaoWidgets = $this->getGestaoWidgets();
                    $fullWidthWidgets = collect($gestaoWidgets)->filter(
                        fn ($widget) => is_a($widget, \Filament\Widgets\StatsOverviewWidget::class, true)
                    );
                    $gridWidgets = collect($gestaoWidgets)->diff($fullWidthWidgets);

                    // Só o segmento "generico" (sem Eventos/Construcao Civil/
                    // Industrial-Hospitalar, mesmo default de
                    // SegmentDashboardWidgets::forSegment()) usa 3 colunas --
                    // pedido explicito do usuario pra Ativos por Status +
                    // Manutencoes por Status + Custo de Manutencao ficarem na
                    // mesma linha (nessa ordem, ja e a ordem real do array).
                    // Os outros 3 segmentos continuam em 2 colunas, sem tocar
                    // no layout deles.
                    $segmentoGenerico = ! in_array(\App\Support\Tenancy::current()?->segment, [
                        \App\Models\Client::NICHE_EVENTOS,
                        \App\Models\Client::NICHE_CONSTRUCAO_CIVIL,
                        \App\Models\Client::NICHE_INDUSTRIAL_HOSPITALAR,
                    ], true);
                    $gridColsClass = $segmentoGenerico ? 'lg:grid-cols-3' : 'lg:grid-cols-2';
                @endphp

                @foreach($fullWidthWidgets as $widget)
                    @livewire($widget)
                @endforeach

                @if($gridWidgets->isNotEmpty())
                    <div class="grid grid-cols-1 {{ $gridColsClass }} gap-3">
                        @foreach($gridWidgets as $widget)
                            @livewire($widget, [], $widget)
                        @endforeach
                    </div>
                @endif
            @else
                {{-- CENTRO DE COMANDO --}}
                <div class="rounded-xl bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5 p-3">
                    <h2 class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">
                        <x-heroicon-o-clipboard-document-list class="w-3.5 h-3.5" />
                        Minhas Ordens de Serviço
                    </h2>
                    @livewire(\App\Filament\Widgets\TechnicianOrderStats::class)
                </div>

                @livewire(\App\Filament\Widgets\RadarUrgencia::class)

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @livewire(\App\Filament\Widgets\ScheduledDispatchesWidget::class)
                    @livewire(\App\Filament\Widgets\CompletedOrdersLast7DaysChart::class)
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div class="md:col-span-3 rounded-xl bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5 p-3 min-h-[300px]">
                        <h2 class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">
                            <x-heroicon-o-exclamation-triangle class="w-3.5 h-3.5" />
                            Lista de Ativos — Alerta Visual
                        </h2>
                        @livewire(\App\Filament\Widgets\ListaAlertaAtivos::class)
                    </div>
                    <div class="md:col-span-1 rounded-xl bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5 p-3 min-h-[300px]">
                        <h2 class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-2">
                            <x-heroicon-o-calendar class="w-3.5 h-3.5" />
                            Agenda de Campo
                        </h2>
                        @livewire(\App\Filament\Widgets\AgendaCampo::class)
                    </div>
                </div>
            @endif
        </div>
    </div>
    </div>
</x-filament-panels::page>
