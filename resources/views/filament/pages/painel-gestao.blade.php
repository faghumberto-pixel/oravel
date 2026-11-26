<x-filament-panels::page>
    @php
        $tenantName = \App\Support\Tenancy::current()?->name;
    @endphp

    {{-- Padrao "sala de controle" (2026-09-19, pedido do usuario -- todas as
         dashboards no mesmo modo escuro fixo, nao seguem o toggle
         claro/escuro do painel). Wrapper .dark escopado cobre a pagina
         inteira, os 2 tabs. --}}
    <div>
    <div class="max-w-full flex flex-col gap-3 rounded-2xl bg-white dark:bg-gray-900 p-3 text-gray-900 dark:text-gray-100 ring-1 ring-gray-950/5 dark:ring-white/5">

        {{-- ===================== CABEÇALHO COMPACTO ===================== --}}
        <div class="flex items-center justify-between gap-3 rounded-xl bg-gray-100 dark:bg-gray-800/60 backdrop-blur-sm px-4 py-2.5 ring-1 ring-gray-950/5 dark:ring-white/5">
            <div class="min-w-0">
                <p class="text-[11px] font-bold uppercase tracking-wider text-gray-900 dark:text-gray-100 truncate">Painel de Controle</p>
                <p class="text-[10px] text-gray-500 dark:text-gray-400 truncate">{{ $tenantName ?? 'Visão Geral' }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <span class="hidden sm:inline text-[10px] font-medium text-gray-500 tabular-nums">{{ now()->format('d/m/Y H:i') }}</span>
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-[10px] font-semibold uppercase tracking-wide text-emerald-400">Ao vivo</span>
            </div>
        </div>

        {{-- ===================== ABAS (segmented control compacto) ===================== --}}
        <div class="inline-flex self-start gap-1 rounded-lg bg-gray-100 dark:bg-gray-800/60 backdrop-blur-sm ring-1 ring-gray-950/5 dark:ring-white/5 p-1">
            <button
                type="button"
                wire:click="selectTab('gestao')"
                class="px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-wide rounded-md transition {{ $activeTab === 'gestao' ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-200' }}"
            >
                Painel de Gestão
            </button>
            <button
                type="button"
                wire:click="selectTab('comando')"
                class="px-3.5 py-1.5 text-[11px] font-semibold uppercase tracking-wide rounded-md transition {{ $activeTab === 'comando' ? 'bg-amber-500 text-white shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-200' }}"
            >
                Gestão à Vista
            </button>
        </div>

        {{-- ===================== ÁREA DE CONTEÚDO ===================== --}}
        <div class="w-full flex flex-col gap-3">
            @if($activeTab === 'gestao' && ($resumoEmpresa = $this->getVisaoEmpresa()))
                {{-- PAINEL DE GESTAO -- visão da empresa inteira: resumo + uma seção por área que o contrato cobre (App\Dashboards\VisaoEmpresa) --}}
                @include('dashboards.empresa', ['resumo' => $resumoEmpresa])
            @elseif($activeTab === 'gestao')
                {{-- PAINEL DE GESTAO (padrão antigo, só quando os dashboards por área não estão liberados no contrato) -- widgets exclusivos do segmento do tenant
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
            @elseif($activeTab === 'comando' && ($gavComercial = $this->getGestaoComercial()))
                {{-- GESTÃO À VISTA para contrato sem manutenção (ex.: pacote Vendas): indicadores comerciais --}}
                @include('dashboards.gestao-comercial', ['blocks' => $gavComercial])
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
                <div class="rounded-xl bg-gray-100 dark:bg-gray-800/60 backdrop-blur-sm ring-1 ring-gray-950/5 dark:ring-white/5 p-3">
                    <div class="mb-2 flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <p class="text-[13px] font-bold uppercase tracking-wide text-gray-900 dark:text-gray-100">Indicadores de Resultados – Manutenção</p>
                            <p class="text-[10px] uppercase tracking-wider text-gray-500">Dados que geram confiabilidade e resultados</p>
                        </div>
                        <div class="inline-flex gap-1 rounded-lg bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 p-0.5" role="group" aria-label="Período">
                            @foreach ([1 => 'Este mês', 3 => '3 meses', 6 => '6 meses', 12 => '12 meses'] as $meses => $rotulo)
                                <button type="button" wire:click="periodo({{ $meses }})"
                                    class="px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide rounded-md transition {{ $this->periodoAtivo() === $meses ? 'bg-amber-500 text-white' : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white' }}">{{ $rotulo }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2 items-end">
                        <div>
                            <label class="block text-[9px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Data Início</label>
                            <input type="date" wire:model="gestaoFrom" class="w-full rounded-lg bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 text-[12px] py-1.5 focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-[9px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Data Fim</label>
                            <input type="date" wire:model="gestaoUntil" class="w-full rounded-lg bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 text-[12px] py-1.5 focus:border-indigo-500 focus:ring-indigo-500" />
                        </div>
                        <div>
                            <label class="block text-[9px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Unidade</label>
                            <select wire:model="gestaoBranchId" class="w-full rounded-lg bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 text-[12px] py-1.5 focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Todas</option>
                                @foreach ($this->getGestaoBranches() as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[9px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Equipamento</label>
                            <select wire:model="gestaoAssetId" class="w-full rounded-lg bg-white dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-gray-900 dark:text-gray-100 text-[12px] py-1.5 focus:border-indigo-500 focus:ring-indigo-500">
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

                {{-- Linhas de largura total (sem coluna lateral fixa: ela espremia os resumos de OS e deixava vazio embaixo).
                     Cada linha tem colunas de mesma altura e o contêiner .gav faz o cartão de cada widget preencher a célula. --}}
                <style>
                    .gav .fi-wi-widget{display:flex;flex-direction:column;min-width:0;grid-column:auto!important}
                    .gav .fi-wi-widget>*{flex:1 1 auto}
                    .gav .gav-linha{display:grid;gap:.75rem;grid-template-columns:minmax(0,1fr)}
                    @media (min-width:640px){.gav .gav-3{grid-template-columns:repeat(2,minmax(0,1fr))}}
                    @media (min-width:1024px){.gav .gav-3{grid-template-columns:repeat(3,minmax(0,1fr))}.gav .gav-2{grid-template-columns:repeat(2,minmax(0,1fr))}.gav .gav-resumo{grid-template-columns:minmax(0,3fr) minmax(0,1fr)}}
                    .gav .gav-col{display:flex;flex-direction:column;gap:.5rem;min-width:0}
                    .gav .gav-col>.fi-wi-widget:last-child{flex:1 1 auto}
                </style>

                <div class="gav flex flex-col gap-3">
                    {{-- RESUMO: ordens de serviço (3 números lado a lado) + custo total --}}
                    <div class="gav-linha gav-resumo">
                        @livewire(\App\Filament\Widgets\GestaoAVista\OsResumoStats::class, $gestaoFiltros, key($gestaoKeyBase.'-resumo'))
                        @livewire(\App\Filament\Widgets\GestaoAVista\CustoTotalMetricCard::class, $gestaoFiltros, key($gestaoKeyBase.'-custo'))
                    </div>

                    {{-- INDICADORES PRINCIPAIS: realizada, disponibilidade e efetividade (medidor + evolução) --}}
                    <div class="gav-linha gav-3">
                        <div class="gav-col">
                            @livewire(\App\Filament\Widgets\GestaoAVista\ManutencaoRealizadaGauge::class, $gestaoFiltros, key($gestaoKeyBase.'-mr-gauge'))
                            @livewire(\App\Filament\Widgets\GestaoAVista\ManutencaoRealizadaEvolucao::class, $gestaoFiltros, key($gestaoKeyBase.'-mr-evo'))
                        </div>
                        <div class="gav-col">
                            @livewire(\App\Filament\Widgets\GestaoAVista\DisponibilidadeGauge::class, $gestaoFiltros, key($gestaoKeyBase.'-disp-gauge'))
                            @livewire(\App\Filament\Widgets\GestaoAVista\DisponibilidadeEvolucao::class, $gestaoFiltros, key($gestaoKeyBase.'-disp-evo'))
                        </div>
                        <div class="gav-col">
                            @livewire(\App\Filament\Widgets\GestaoAVista\EfetividadeGauge::class, $gestaoFiltros, key($gestaoKeyBase.'-efet-gauge'))
                            @livewire(\App\Filament\Widgets\GestaoAVista\EfetividadeEvolucao::class, $gestaoFiltros, key($gestaoKeyBase.'-efet-evo'))
                        </div>
                    </div>

                    {{-- CONFIABILIDADE: MTBF, MTTR e tempo de parada --}}
                    <div class="gav-linha gav-3">
                        @livewire(\App\Filament\Widgets\GestaoAVista\MtbfMetricCard::class, $gestaoFiltros, key($gestaoKeyBase.'-mtbf'))
                        @livewire(\App\Filament\Widgets\GestaoAVista\MttrMetricCard::class, $gestaoFiltros, key($gestaoKeyBase.'-mttr'))
                        @livewire(\App\Filament\Widgets\GestaoAVista\TempoParadaMetricCard::class, $gestaoFiltros, key($gestaoKeyBase.'-parada'))
                    </div>

                    {{-- ONDE ESTÁ O PROBLEMA: tipos de manutenção e causas de falha --}}
                    <div class="gav-linha gav-2">
                        @livewire(\App\Filament\Widgets\GestaoAVista\TiposManutencaoAreaChart::class, $gestaoFiltros, key($gestaoKeyBase.'-tipos'))
                        @livewire(\App\Filament\Widgets\GestaoAVista\CausasFalhaBarChart::class, $gestaoFiltros, key($gestaoKeyBase.'-causas'))
                    </div>

                    {{-- FECHAMENTO: evolução da parada + conclusões --}}
                    <div class="gav-linha gav-2">
                        @livewire(\App\Filament\Widgets\GestaoAVista\TempoParadaEvolucaoAreaChart::class, $gestaoFiltros, key($gestaoKeyBase.'-parada-evo'))
                        @livewire(\App\Filament\Widgets\GestaoAVista\ConclusoesPanel::class, $gestaoFiltros, key($gestaoKeyBase.'-conclusoes'))
                    </div>

                    {{-- BANNER DE RODAPÉ --}}
                    <div class="rounded-xl bg-gradient-to-r from-indigo-950/60 via-gray-800/60 to-indigo-950/60 ring-1 ring-gray-950/5 dark:ring-white/5 p-4 text-center">
                        <p class="text-[12px] font-bold uppercase tracking-wide text-gray-800 dark:text-gray-200">Dados não tomam decisões. Pessoas informadas, sim.</p>
                        <p class="text-[10px] uppercase tracking-wider text-indigo-300 mt-1">Gestão à vista, decisão na hora, resultado todo dia.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
    </div>
</x-filament-panels::page>
