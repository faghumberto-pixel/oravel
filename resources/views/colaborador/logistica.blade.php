@extends('layouts.colaborador')
@section('titulo', 'Logística - Oravel Colaborador')
@section('conteudo')
    <a href="{{ route('colab.home') }}" class="text-sm font-semibold text-zinc-400">← Início</a>
    <h1 class="mt-3 text-xl font-bold">Logística</h1>

    @if($resumo['vencidos'] > 0)
        <a href="{{ route('colab.logistica.vencimentos') }}" class="mt-4 block rounded-xl bg-red-500/15 p-3 text-sm font-bold text-red-400">⚠ {{ $resumo['vencidos'] }} veículo(s) com documento vencido</a>
    @endif

    <div class="mt-4 space-y-3">
        @forelse($funcoes as $f)
            <a href="{{ route($f['rota']) }}" class="flex items-center gap-4 rounded-2xl bg-zinc-900 p-4 active:bg-zinc-800">
                <span class="text-3xl">{{ $f['icone'] }}</span>
                <span class="flex-1">
                    <span class="block text-base font-bold">{{ $f['titulo'] }}</span>
                    <span class="block text-xs text-zinc-400">{{ $f['texto'] }}</span>
                </span>
                @if($f['selo'] > 0)<span class="rounded-full bg-orange-500 px-2 py-0.5 text-center text-xs font-bold text-white">{{ $f['selo'] }} {{ $f['selo_texto'] }}</span>@endif
            </a>
        @empty
            <p class="rounded-xl bg-zinc-900 p-4 text-sm text-zinc-400">Nenhuma função de Logística liberada para o seu perfil. Fale com o administrador da sua empresa.</p>
        @endforelse
    </div>
@endsection
