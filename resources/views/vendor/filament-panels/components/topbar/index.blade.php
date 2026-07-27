{{--
    Override de vendor/filament/filament/resources/views/components/topbar/index.blade.php
    (Laravel resolve resources/views/vendor/{package}/... antes do pacote, sem tocar em vendor/).

    Esse componente e' compartilhado por TODOS os paineis Filament do app
    (namespace filament-panels::), nao so' o admin -- por isso o
    @if (filament()->getId() === 'admin') abaixo: o painel `central` nunca
    usou ->topNavigation() e tem seu proprio relogio
    (filament.central.topbar-clock), sem pedido de mudanca nenhuma aqui.
    Sem essa condicional, a reestruturacao do admin vazaria pro central
    tambem (o relogio dele sumiria, apareceria uma barra cinza vazia, etc).

    Historico: a classe "dark" fixa na div externa ja vinha de uma versao
    anterior deste mesmo override -- Tailwind com darkMode:'class' gera
    regras tipo ".dark .dark\:bg-gray-900{...}", que so exigem um
    ANCESTRAL com classe "dark" (nao precisa ser o <html>). Isso forca
    dropdown/busca global/sino/avatar (componentes que usam dark: por
    baixo) a sempre renderizar no visual escuro. Mantido pros dois ramos.

    Mudanca nova (2026-07-25, so' admin): topbar (logo/tenant/relogio/
    avisos) e menu (grupos + busca/sino/perfil/ajuda) viraram 2 elementos
    HTML de verdade em vez de 1 <nav> so' com flex-wrap+CSS. Tentar simular
    2 linhas so' com CSS por cima do HTML padrao (flex-wrap, margem
    negativa, JS de scroll pra "esconder ao rolar") gerou bug (barra
    desproporcional, tremor no scroll, texto sobreposto) -- com 2
    elementos reais, "topbar some ao descer" e' scroll comum (nao e'
    sticky, sai da tela sozinho) e "menu sempre visivel" e'
    position:sticky nativo do <nav>, sem 1 linha de JS.

    Atencao: um update do pacote filament/filament que mude o arquivo
    original NAO vai refletir aqui automaticamente, em nenhum dos 2 ramos.

    Mudanca nova (2026-07-26, so' ramo admin): itens com childItems() (ex.
    "Almoxarifado"/"Compras" em Ativos e Materiais) paravam de ser um
    dropdown de verdade -- o componente nativo do Filament em modo topbar
    so' sabe achatar pai+filhos numa lista so', sempre expandida, sem seta
    (ver historico do componente original: o mesmo bloco de flatten existia
    la'). Substituido por um toggle Alpine local (x-data="{ open: false }")
    por item-pai, fechado por padrao, com chevron que gira -- mesmo icone/
    transicao que o proprio Filament usa no grupo colapsavel da sidebar
    (components/sidebar/group.blade.php), so' que aqui e' por item dentro
    do dropdown, nao o dropdown inteiro.
--}}
@props([
    'navigation',
])

@if (filament()->getId() === 'admin')
    <div
        {{
            $attributes->class([
                'fi-topbar overflow-x-clip dark',
            ])
        }}
    >
        {{-- Linha 1: topbar de verdade -- fluxo normal da pagina (nao e'
             sticky), some ao rolar pra baixo e volta ao rolar pra cima,
             sem nenhum JS. Conteudo vem de
             filament.topbar-brand-and-ticker via TOPBAR_START (logo+
             tenant, relogio, avisos).

             fi-oravel-topbar-row1: classe usada em CSS puro (ver
             brand-header-background.blade.php) pro espacamento entre os
             filhos -- classes novas do Tailwind tipo "gap-x-10"/"me-10"
             NAO tem efeito aqui: o Vite nao builda neste ambiente (Node
             desatualizado), entao o CSS compilado em public/build so'
             contem as classes que ja existiam ANTES desta sessao. --}}
        <div class="fi-oravel-topbar-row1 hidden bg-black px-4 md:px-6 lg:flex lg:items-center lg:px-8">
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_START) }}
        </div>

        {{-- Linha 2: o menu de verdade -- sticky sozinho, sempre visivel.
             Transparente (pedido do usuario 2026-07-26): topo preto, barra
             de menu transparente, footer transparente. --}}
        <nav
            class="sticky top-0 z-20 flex h-16 items-center gap-x-4 bg-transparent px-4 shadow-sm ring-1 ring-white/10 md:px-6 lg:px-8"
        >
            @if (filament()->hasNavigation())
                <x-filament::icon-button
                    color="gray"
                    icon="heroicon-o-bars-3"
                    icon-alias="panels::topbar.open-sidebar-button"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.expand.label')"
                    x-cloak
                    x-data="{}"
                    x-on:click="$store.sidebar.open()"
                    x-show="! $store.sidebar.isOpen"
                    @class([
                        'fi-topbar-open-sidebar-btn',
                        'lg:hidden' => (! filament()->isSidebarFullyCollapsibleOnDesktop()) || filament()->isSidebarCollapsibleOnDesktop(),
                    ])
                />

                <x-filament::icon-button
                    color="gray"
                    icon="heroicon-o-x-mark"
                    icon-alias="panels::topbar.close-sidebar-button"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                    x-cloak
                    x-data="{}"
                    x-on:click="$store.sidebar.close()"
                    x-show="$store.sidebar.isOpen"
                    class="fi-topbar-close-sidebar-btn lg:hidden"
                />
            @endif

            @if (filament()->hasTopNavigation() || (! filament()->hasNavigation()))
                @if (filament()->hasTenancy() && filament()->hasTenantMenu())
                    <x-filament-panels::tenant-menu class="hidden lg:block" />
                @endif

                @if (filament()->hasNavigation())
                    <ul class="me-4 hidden items-center gap-x-4 lg:flex">
                        @foreach ($navigation as $group)
                            @if ($groupLabel = $group->getLabel())
                                <x-filament::dropdown
                                    placement="bottom-start"
                                    teleport
                                    :attributes="\Filament\Support\prepare_inherited_attributes($group->getExtraTopbarAttributeBag())"
                                >
                                    <x-slot name="trigger">
                                        <x-filament-panels::topbar.item
                                            :active="$group->isActive()"
                                            :icon="$group->getIcon()"
                                        >
                                            {{ $groupLabel }}
                                        </x-filament-panels::topbar.item>
                                    </x-slot>

                                    @php
                                        $lists = [];

                                        foreach ($group->getItems() as $item) {
                                            if ($childItems = $item->getChildItems()) {
                                                $lists[] = ['type' => 'group', 'parent' => $item, 'children' => $childItems];
                                                $lists[] = ['type' => 'flat', 'items' => []];

                                                continue;
                                            }

                                            if (empty($lists) || ($lists[count($lists) - 1]['type'] ?? null) !== 'flat') {
                                                $lists[] = ['type' => 'flat', 'items' => [$item]];

                                                continue;
                                            }

                                            $lists[count($lists) - 1]['items'][] = $item;
                                        }

                                        if (($lists[count($lists) - 1]['type'] ?? null) === 'flat' && empty($lists[count($lists) - 1]['items'])) {
                                            array_pop($lists);
                                        }
                                    @endphp

                                    @foreach ($lists as $list)
                                        @if ($list['type'] === 'group')
                                            {{-- Flyout lateral (nao accordion pra baixo, pedido explicito do
                                                 usuario 2026-07-26): x-data local com posicionamento absolute
                                                 left-full, nao x-collapse. Fecha sozinho em @click.outside. --}}
                                            <div class="fi-dropdown-list relative p-1" x-data="{ open: false }" x-on:click.outside="open = false">
                                                <button
                                                    type="button"
                                                    x-on:click="open = ! open"
                                                    class="fi-dropdown-list-item flex w-full items-center gap-2 whitespace-nowrap rounded-md p-2 text-sm outline-none transition-colors duration-75 hover:bg-gray-50 dark:hover:bg-white/5"
                                                >
                                                    @if ($icon = $list['parent']->getIcon())
                                                        <x-filament::icon
                                                            :icon="$icon"
                                                            class="fi-dropdown-list-item-icon h-5 w-5 text-gray-400 dark:text-gray-500"
                                                        />
                                                    @endif

                                                    <span class="fi-dropdown-list-item-label flex-1 truncate text-start text-gray-700 dark:text-gray-200">
                                                        {{ $list['parent']->getLabel() }}
                                                    </span>

                                                    <x-filament::icon
                                                        icon="heroicon-m-chevron-right"
                                                        class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500"
                                                    />
                                                </button>

                                                <ul
                                                    x-show="open"
                                                    x-cloak
                                                    x-transition:enter="transition ease-out duration-100"
                                                    x-transition:enter-start="opacity-0 -translate-x-1"
                                                    x-transition:enter-end="opacity-100 translate-x-0"
                                                    class="fi-dropdown-panel absolute start-full top-0 z-20 ms-1 flex w-56 flex-col divide-y divide-gray-100 rounded-lg bg-white p-1 shadow-lg ring-1 ring-gray-950/5 dark:divide-white/5 dark:bg-gray-900 dark:ring-white/10"
                                                >
                                                    @foreach ($list['children'] as $childItem)
                                                        @php
                                                            $childItemIsActive = $childItem->isActive();
                                                        @endphp

                                                        <x-filament::dropdown.list.item
                                                            :badge="$childItem->getBadge()"
                                                            :badge-color="$childItem->getBadgeColor()"
                                                            :badge-tooltip="$childItem->getBadgeTooltip()"
                                                            :color="$childItemIsActive ? 'primary' : 'gray'"
                                                            :href="$childItem->getUrl()"
                                                            :icon="$childItemIsActive ? ($childItem->getActiveIcon() ?? $childItem->getIcon()) : $childItem->getIcon()"
                                                            tag="a"
                                                            :target="$childItem->shouldOpenUrlInNewTab() ? '_blank' : null"
                                                        >
                                                            {{ $childItem->getLabel() }}
                                                        </x-filament::dropdown.list.item>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @else
                                            <x-filament::dropdown.list>
                                                @foreach ($list['items'] as $item)
                                                    @php
                                                        $itemIsActive = $item->isActive();
                                                    @endphp

                                                    <x-filament::dropdown.list.item
                                                        :badge="$item->getBadge()"
                                                        :badge-color="$item->getBadgeColor()"
                                                        :badge-tooltip="$item->getBadgeTooltip()"
                                                        :color="$itemIsActive ? 'primary' : 'gray'"
                                                        :href="$item->getUrl()"
                                                        :icon="$itemIsActive ? ($item->getActiveIcon() ?? $item->getIcon()) : $item->getIcon()"
                                                        tag="a"
                                                        :target="$item->shouldOpenUrlInNewTab() ? '_blank' : null"
                                                    >
                                                        {{ $item->getLabel() }}
                                                    </x-filament::dropdown.list.item>
                                                @endforeach
                                            </x-filament::dropdown.list>
                                        @endif
                                    @endforeach
                                </x-filament::dropdown>
                            @else
                                @foreach ($group->getItems() as $item)
                                    <x-filament-panels::topbar.item
                                        :active="$item->isActive()"
                                        :active-icon="$item->getActiveIcon()"
                                        :badge="$item->getBadge()"
                                        :badge-color="$item->getBadgeColor()"
                                        :badge-tooltip="$item->getBadgeTooltip()"
                                        :icon="$item->getIcon()"
                                        :should-open-url-in-new-tab="$item->shouldOpenUrlInNewTab()"
                                        :url="$item->getUrl()"
                                    >
                                        {{ $item->getLabel() }}
                                    </x-filament-panels::topbar.item>
                                @endforeach
                            @endif
                        @endforeach
                    </ul>
                @endif
            @endif

            <div
                @if (filament()->hasTenancy())
                    x-persist="topbar.end.panel-{{ filament()->getId() }}.tenant-{{ filament()->getTenant()?->getKey() }}"
                @else
                    x-persist="topbar.end.panel-{{ filament()->getId() }}"
                @endif
                class="flex items-center gap-x-6 lg:ms-6"
            >
                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_BEFORE) }}

                @if (filament()->isGlobalSearchEnabled())
                    @livewire(Filament\Livewire\GlobalSearch::class)
                @endif

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_AFTER) }}

                @if (filament()->auth()->check())
                    @if (filament()->hasDatabaseNotifications())
                        @livewire(Filament\Livewire\DatabaseNotifications::class, [
                            'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                        ])
                    @endif

                    <x-filament-panels::user-menu />
                @endif
            </div>

            <div class="ms-2 flex items-center">
                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_END) }}
            </div>
        </nav>
    </div>
@else
    {{-- Painel central (ou qualquer outro que nao seja o admin): mantido
         exatamente como estava antes da mudanca de 2026-07-25, sem
         nenhuma das mudancas de layout acima. --}}
    <div
        {{
            $attributes->class([
                'fi-topbar sticky top-0 z-20 overflow-x-clip dark',
                'fi-topbar-with-navigation' => filament()->hasTopNavigation(),
            ])
        }}
    >
        <nav
            class="flex h-16 items-center gap-x-4 bg-white px-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 md:px-6 lg:px-8"
        >
            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_START) }}

            @if (filament()->hasNavigation())
                <x-filament::icon-button
                    color="gray"
                    icon="heroicon-o-bars-3"
                    icon-alias="panels::topbar.open-sidebar-button"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.expand.label')"
                    x-cloak
                    x-data="{}"
                    x-on:click="$store.sidebar.open()"
                    x-show="! $store.sidebar.isOpen"
                    @class([
                        'fi-topbar-open-sidebar-btn',
                        'lg:hidden' => (! filament()->isSidebarFullyCollapsibleOnDesktop()) || filament()->isSidebarCollapsibleOnDesktop(),
                    ])
                />

                <x-filament::icon-button
                    color="gray"
                    icon="heroicon-o-x-mark"
                    icon-alias="panels::topbar.close-sidebar-button"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                    x-cloak
                    x-data="{}"
                    x-on:click="$store.sidebar.close()"
                    x-show="$store.sidebar.isOpen"
                    class="fi-topbar-close-sidebar-btn lg:hidden"
                />
            @endif

            @if (filament()->hasTopNavigation() || (! filament()->hasNavigation()))
                <div class="me-6 hidden lg:flex">
                    @if ($homeUrl = filament()->getHomeUrl())
                        <a {{ \Filament\Support\generate_href_html($homeUrl) }}>
                            <x-filament-panels::logo />
                        </a>
                    @else
                        <x-filament-panels::logo />
                    @endif
                </div>

                @if (filament()->hasTenancy() && filament()->hasTenantMenu())
                    <x-filament-panels::tenant-menu class="hidden lg:block" />
                @endif

                @if (filament()->hasNavigation())
                    <ul class="me-4 hidden items-center gap-x-4 lg:flex">
                        @foreach ($navigation as $group)
                            @if ($groupLabel = $group->getLabel())
                                <x-filament::dropdown
                                    placement="bottom-start"
                                    teleport
                                    :attributes="\Filament\Support\prepare_inherited_attributes($group->getExtraTopbarAttributeBag())"
                                >
                                    <x-slot name="trigger">
                                        <x-filament-panels::topbar.item
                                            :active="$group->isActive()"
                                            :icon="$group->getIcon()"
                                        >
                                            {{ $groupLabel }}
                                        </x-filament-panels::topbar.item>
                                    </x-slot>

                                    @php
                                        $lists = [];

                                        foreach ($group->getItems() as $item) {
                                            if ($childItems = $item->getChildItems()) {
                                                $lists[] = [
                                                    $item,
                                                    ...$childItems,
                                                ];
                                                $lists[] = [];

                                                continue;
                                            }

                                            if (empty($lists)) {
                                                $lists[] = [$item];

                                                continue;
                                            }

                                            $lists[count($lists) - 1][] = $item;
                                        }

                                        if (empty($lists[count($lists) - 1])) {
                                            array_pop($lists);
                                        }
                                    @endphp

                                    @foreach ($lists as $list)
                                        <x-filament::dropdown.list>
                                            @foreach ($list as $item)
                                                @php
                                                    $itemIsActive = $item->isActive();
                                                @endphp

                                                <x-filament::dropdown.list.item
                                                    :badge="$item->getBadge()"
                                                    :badge-color="$item->getBadgeColor()"
                                                    :badge-tooltip="$item->getBadgeTooltip()"
                                                    :color="$itemIsActive ? 'primary' : 'gray'"
                                                    :href="$item->getUrl()"
                                                    :icon="$itemIsActive ? ($item->getActiveIcon() ?? $item->getIcon()) : $item->getIcon()"
                                                    tag="a"
                                                    :target="$item->shouldOpenUrlInNewTab() ? '_blank' : null"
                                                >
                                                    {{ $item->getLabel() }}
                                                </x-filament::dropdown.list.item>
                                            @endforeach
                                        </x-filament::dropdown.list>
                                    @endforeach
                                </x-filament::dropdown>
                            @else
                                @foreach ($group->getItems() as $item)
                                    <x-filament-panels::topbar.item
                                        :active="$item->isActive()"
                                        :active-icon="$item->getActiveIcon()"
                                        :badge="$item->getBadge()"
                                        :badge-color="$item->getBadgeColor()"
                                        :badge-tooltip="$item->getBadgeTooltip()"
                                        :icon="$item->getIcon()"
                                        :should-open-url-in-new-tab="$item->shouldOpenUrlInNewTab()"
                                        :url="$item->getUrl()"
                                    >
                                        {{ $item->getLabel() }}
                                    </x-filament-panels::topbar.item>
                                @endforeach
                            @endif
                        @endforeach
                    </ul>
                @endif
            @endif

            <div
                @if (filament()->hasTenancy())
                    x-persist="topbar.end.panel-{{ filament()->getId() }}.tenant-{{ filament()->getTenant()?->getKey() }}"
                @else
                    x-persist="topbar.end.panel-{{ filament()->getId() }}"
                @endif
                class="ms-auto flex items-center gap-x-4"
            >
                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_BEFORE) }}

                @if (filament()->isGlobalSearchEnabled())
                    @livewire(Filament\Livewire\GlobalSearch::class)
                @endif

                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_AFTER) }}

                @if (filament()->auth()->check())
                    @if (filament()->hasDatabaseNotifications())
                        @livewire(Filament\Livewire\DatabaseNotifications::class, [
                            'lazy' => filament()->hasLazyLoadedDatabaseNotifications(),
                        ])
                    @endif

                    <x-filament-panels::user-menu />
                @endif
            </div>

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::TOPBAR_END) }}
        </nav>
    </div>
@endif
