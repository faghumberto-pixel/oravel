{{-- Estrutura padrao dos apps mobile: cabecalho azul, busca flutuante, abas, conteudo e barra de icones.
     Prop nav=false esconde a barra de icones (ex.: dentro do app do colaborador). Slots opcionais: actions (botao do cabecalho), search, tabs, bar (acoes fixas acima do rodape), fab. Usa zinc-* (nao gray-*): as variaveis
     --gray-N do Filament nao existem neste layout. --}}
@props(['title', 'subtitle' => null, 'active' => null, 'back' => null, 'nav' => true])

<div class="absolute inset-0 mx-auto flex max-w-md flex-col bg-zinc-50 text-zinc-900">
    <div class="flex-1 overflow-y-auto">
        <header class="rounded-b-3xl bg-gradient-to-r from-blue-600 to-blue-500 px-5 pb-8 pt-6 text-white">
            @if ($back)
                <a href="{{ $back }}" class="mb-3 inline-block text-sm font-semibold text-blue-100">← Voltar</a>
            @endif
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="truncate text-3xl font-bold">{{ $title }}</h1>
                    @if ($subtitle)
                        <p class="mt-1 text-sm text-blue-100">{{ $subtitle }}</p>
                    @endif
                </div>
                @isset($actions)
                    <div class="shrink-0">{{ $actions }}</div>
                @endisset
            </div>
        </header>

        @isset($search)
            <div class="relative z-10 -mt-4 px-5">{{ $search }}</div>
        @endisset

        @isset($tabs)
            <div class="px-5 pt-5">{{ $tabs }}</div>
        @endisset

        <main class="space-y-3 px-5 pb-28 pt-5">{{ $slot }}</main>
    </div>

    @isset($fab)
        {{ $fab }}
    @endisset

    @isset($bar)
        <div class="shrink-0 space-y-2 border-t border-zinc-200 bg-white px-4 py-3">{{ $bar }}</div>
    @endisset

    @if ($nav)
        <x-mobile.bottom-nav :active="$active" />
    @endif
</div>
