{{-- Barra de icones do rodape dos apps mobile. Itens por perfil/permissao: App\Support\MobileNav. --}}
@props(['active' => null])

@php
    $icones = [
        'casa' => '<path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"/>',
        'lista' => '<path d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"/>',
        'pessoa' => '<path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM9 10a9 9 0 018.946 7.5H2a9 9 0 018.946-7.5z"/>',
        'calendario' => '<path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v2h16V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"/>',
        'relogio' => '<path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"/>',
        'mais' => '<path d="M6 10a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0zm6 0a2 2 0 11-4 0 2 2 0 014 0z"/>',
    ];
@endphp

<nav class="flex h-20 shrink-0 items-center justify-around border-t border-zinc-200 bg-white" aria-label="Navegação principal">
    @foreach (\App\Support\MobileNav::itens(auth()->user()) as $item)
        @php($ativo = $active ? $item['key'] === $active : url()->current() === strtok($item['url'], '?'))
        <a href="{{ $item['url'] }}"
           @if ($ativo) aria-current="page" @endif
           class="flex h-full w-full flex-col items-center justify-center transition {{ $ativo ? 'text-blue-600' : 'text-zinc-600 hover:text-blue-600' }}">
            <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">{!! $icones[$item['icon']] !!}</svg>
            <span class="mt-1 text-xs">{{ $item['label'] }}</span>
        </a>
    @endforeach
</nav>
