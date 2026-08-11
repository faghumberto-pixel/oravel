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
                Gestão à Vista
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
                {{-- GESTÃO À VISTA -- indicadores de resultado de manutenção. Todos os
                     widgets abaixo recebem os mesmos 4 filtros via @livewire(...) e
                     usam :key com $gestaoRefreshTick pra reinstanciar (e rechamar
                     mount(), refazendo as queries) quando "ATUALIZAR DADOS" é clicado,
                     mesmo sem nenhuma propriedade pública do widget filho ter mudado
                     sozinha. --}}
                @php
                    $gestaoFiltros = $this->getGestaoFiltros();
                    $gestaoKeyBase = 'gav-'.$gestaoRefreshTick.'-'.md5(json_encode($gestaoFiltros));
                @endphp

                {{-- Cabeçalho + Filtros --}}
                <div class="rounded-xl bg-gray-800/60 backdrop-blur-sm ring-1 ring-white/5 p-3">
                    <div class="mb-2">
                        <p class="text-[13px] font-bold uppercase tracking-wide text-gray-100">Indicadores de Resultados – Manutenção</p>
                        <p class="text-[10px] uppercase tracking-wider text-gray-500">Dados que geram confiabilidade e resultados</p>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2 items-end">
                        <div>
                            <label class="block text-[9px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Data Início</label>
                            <input type="date" wire:model="gestaoFrom" class="w-full rounded-lg bg-gray-900 border-gray-700 text-gray-100 text-[12px] py-1.5 focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-[9px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Data Fim</label>
                            <input type="date" wire:model="gestaoUntil" class="w-full rounded-lg bg-gray-900 border-gray-700 text-gray-100 text-[12px] py-1.5 focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-[9px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Unidade</label>
                            <select wire:model="gestaoBranchId" class="w-full rounded-lg bg-gray-900 border-gray-700 text-gray-100 text-[12px] py-1.5 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Todas</option>
                                @foreach ($this->getGestaoBranches() as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[9px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Equipamento</label>
                            <select wire:model="gestaoAssetId" class="w-full rounded-lg bg-gray-900 border-gray-700 text-gray-100 text-[12px] py-1.5 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Todos</option>
                                @foreach ($this->getGestaoAssets() as $asset)
                                    <option value="{{ $asset->id }}">{{ $asset->name }}{{ $asset->patrimonio ? " ({$asset->patrimonio})" : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button
                            type="button"
                            wire:click="atualizarDados"
                            class="rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-[11px] font-bold uppercase tracking-wide py-2 transition"
                        >
                            Atualizar Dados
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-[280px_1fr] gap-3 items-start">
                    {{-- SIDEBAR ESQUERDA: resumo de OS, tipos, custo total --}}
                    <div class="flex flex-col gap-3">
                        @livewire(\App\Filament\Widgets\GestaoAVista\OsResumoStats::class, $gestaoFiltros, key($gestaoKeyBase.'-resumo'))
                        @livewire(\App\Filament\Widgets\GestaoAVista\TiposManutencaoAreaChart::class, $gestaoFiltros, key($gestaoKeyBase.'-tipos'))
                        @livewire(\App\Filament\Widgets\GestaoAVista\CustoTotalMetricCard::class, $gestaoFiltros, key($gestaoKeyBase.'-custo'))
                    </div>

                    {{-- CONTEÚDO PRINCIPAL --}}
                    <div class="flex flex-col gap-3">
                        {{-- KPIS DE EXECUÇÃO E DISPONIBILIDADE (3 colunas) --}}
                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                            <div class="flex flex-col gap-2">
                                @livewire(\App\Filament\Widgets\GestaoAVista\ManutencaoRealizadaGauge::class, $gestaoFiltros, key($gestaoKeyBase.'-mr-gauge'))
                                @livewire(\App\Filament\Widgets\GestaoAVista\ManutencaoRealizadaEvolucao::class, $gestaoFiltros, key($gestaoKeyBase.'-mr-evo'))
                            </div>
                            <div class="flex flex-col gap-2">
                                @livewire(\App\Filament\Widgets\GestaoAVista\DisponibilidadeGauge::class, $gestaoFiltros, key($gestaoKeyBase.'-disp-gauge'))
                                @livewire(\App\Filament\Widgets\GestaoAVista\DisponibilidadeEvolucao::class, $gestaoFiltros, key($gestaoKeyBase.'-disp-evo'))
                            </div>
                            <div class="flex flex-col gap-2">
                                @livewire(\App\Filament\Widgets\GestaoAVista\EfetividadeGauge::class, $gestaoFiltros, key($gestaoKeyBase.'-efet-gauge'))
                                @livewire(\App\Filament\Widgets\GestaoAVista\EfetividadeEvolucao::class, $gestaoFiltros, key($gestaoKeyBase.'-efet-evo'))
                            </div>
                        </div>

                        {{-- MÉTRICAS OPERACIONAIS E CAUSAS (4 colunas) --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            @livewire(\App\Filament\Widgets\GestaoAVista\MtbfMetricCard::class, $gestaoFiltros, key($gestaoKeyBase.'-mtbf'))
                            @livewire(\App\Filament\Widgets\GestaoAVista\MttrMetricCard::class, $gestaoFiltros, key($gestaoKeyBase.'-mttr'))
                            @livewire(\App\Filament\Widgets\GestaoAVista\TempoParadaMetricCard::class, $gestaoFiltros, key($gestaoKeyBase.'-parada'))
                            @livewire(\App\Filament\Widgets\GestaoAVista\CausasFalhaBarChart::class, $gestaoFiltros, key($gestaoKeyBase.'-causas'))
                        </div>

                        {{-- FECHAMENTO: evolução da parada + conclusões --}}
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                            @livewire(\App\Filament\Widgets\GestaoAVista\TempoParadaEvolucaoAreaChart::class, $gestaoFiltros, key($gestaoKeyBase.'-parada-evo'))
                            @livewire(\App\Filament\Widgets\GestaoAVista\ConclusoesPanel::class, $gestaoFiltros, key($gestaoKeyBase.'-conclusoes'))
                        </div>

                        {{-- BANNER DE RODAPÉ --}}
                        <div class="rounded-xl bg-gradient-to-r from-indigo-950/60 via-gray-800/60 to-indigo-950/60 ring-1 ring-white/5 p-4 text-center">
                            <p class="text-[12px] font-bold uppercase tracking-wide text-gray-200">Dados não tomam decisões. Pessoas informadas, sim.</p>
                            <p class="text-[10px] uppercase tracking-wider text-indigo-300 mt-1">Gestão à vista, decisão na hora, resultado todo dia.</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
    </div>
</x-filament-panels::page>
