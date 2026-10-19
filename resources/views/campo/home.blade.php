@extends('layouts.campo')

@section('titulo', 'Oravel Campo')

@section('conteudo')
    <header class="mb-6">
        <p class="text-sm text-slate-400">{{ $user->tenant?->name }}</p>
        <h1 class="text-2xl font-bold">Olá, {{ \Illuminate\Support\Str::of($user->name)->before(' ') }}</h1>
        <p class="mt-1 text-base text-slate-300">O que você vai fazer agora?</p>
    </header>

    @if (empty($itens))
        <p class="rounded-2xl bg-slate-800 p-5 text-base text-slate-300" data-testid="campo-vazio">
            Ainda não há atalhos de campo para o seu acesso. Peça ao administrador para liberar o seu perfil.
        </p>
    @else
        <nav class="grid grid-cols-2 gap-4" data-testid="campo-atalhos">
            @foreach ($itens as $item)
                <a href="{{ $item['url'] }}"
                    class="flex min-h-40 flex-col justify-between rounded-3xl p-4 shadow-lg active:scale-95"
                    style="background-color: {{ $item['cor'] }}">
                    <x-filament::icon :icon="$item['icone']" class="h-10 w-10 text-white" />
                    <span>
                        <span class="block text-lg font-bold leading-tight">{{ $item['titulo'] }}</span>
                        <span class="mt-1 block text-sm text-white/80">{{ $item['descricao'] }}</span>
                    </span>
                </a>
            @endforeach
        </nav>
    @endif

    <footer class="mt-auto pt-8 text-center">
        <a href="{{ route('campo.alternar', 'completa') }}" class="inline-block rounded-full border border-slate-600 px-5 py-3 text-sm font-semibold text-slate-200">
            Abrir a versão completa
        </a>
    </footer>
@endsection
