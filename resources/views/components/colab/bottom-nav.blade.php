{{-- Barra de icones do rodape do app do colaborador: Inicio + ate 3 funcoes que a pessoa pode usar
     (Modulos::para ja aplica contrato, perfil e permissao). Mesmo padrao visual de x-mobile.bottom-nav. --}}
@php
    $icones = [
        'casa' => '<path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>',
        'lista' => '<path d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"/>',
        'relogio' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>',
        'caminhao' => '<path d="M2 5a1 1 0 011-1h8a1 1 0 011 1v8H2V5zm10 2h3.2a1 1 0 01.8.4l2 2.6a1 1 0 01.2.6V13h-6.2V7z"/><circle cx="6" cy="14.5" r="1.8"/><circle cx="14" cy="14.5" r="1.8"/>',
        'camera' => '<path fill-rule="evenodd" d="M4 5a2 2 0 00-2 2v7a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-1.586a1 1 0 01-.707-.293l-1.121-1.121A2 2 0 0011.172 3H8.828a2 2 0 00-1.414.586L6.293 4.707A1 1 0 015.586 5H4zm6 9a3 3 0 100-6 3 3 0 000 6z"/>',
        'doc' => '<path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"/>',
        'lupa' => '<path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"/>',
    ];
    $preferidos = ['minhas_os' => ['Minhas OS', 'lista'], 'ponto' => ['Ponto', 'relogio'], 'logistica' => ['Logística', 'caminhao'], 'escanear' => ['Ler QR', 'camera'], 'ausencias' => ['Faltas', 'doc'], 'os' => ['OS', 'lupa'], 'dossie' => ['Dossiê', 'lupa']];
    $permitidos = collect(\App\Support\Colaborador\Modulos::para(auth()->user()))->keyBy('chave');
    $itens = [['Início', 'casa', route('colab.home', [], false), 'colab.home']];
    foreach ($preferidos as $chave => [$rotulo, $icone]) {
        if ($permitidos->has($chave) && count($itens) < 4) {
            $itens[] = [$rotulo, $icone, route($permitidos[$chave]['rota'], [], false), $permitidos[$chave]['rota']];
        }
    }
@endphp

<nav class="fixed inset-x-0 bottom-0 z-40 mx-auto flex h-20 max-w-md items-center justify-around border-t border-zinc-200 bg-white" aria-label="Navegação principal">
    @foreach ($itens as [$rotulo, $icone, $url, $rota])
        @php($ativo = request()->routeIs($rota) || request()->routeIs($rota.'.*'))
        <a href="{{ $url }}" @if ($ativo) aria-current="page" @endif
           class="flex h-full w-full flex-col items-center justify-center transition {{ $ativo ? 'text-blue-600' : 'text-zinc-600 hover:text-blue-600' }}">
            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">{!! $icones[$icone] !!}</svg>
            <span class="mt-1 text-xs">{{ $rotulo }}</span>
        </a>
    @endforeach
</nav>
