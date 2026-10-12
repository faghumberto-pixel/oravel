@extends('layouts.colaborador')
@section('titulo', 'Notificações - Oravel Colaborador')
@section('conteudo')
    <a href="{{ route('colab.home') }}" class="text-sm font-semibold text-zinc-400">← Início</a>
    <div class="mt-3 flex items-center justify-between">
        <h1 class="text-xl font-bold">Notificações</h1>
        @if($avisos->isNotEmpty())
            <form method="POST" action="{{ route('colab.notificacoes.limpar') }}" onsubmit="return confirm('Apagar todas as notificações?')">
                @csrf
                <button type="submit" class="rounded-lg border border-zinc-700 px-3 py-2 text-xs font-bold text-zinc-300 active:bg-zinc-800">Limpar tudo</button>
            </form>
        @endif
    </div>

    <div class="mt-4 space-y-2">
        @forelse($avisos as $a)
            <div class="rounded-xl bg-zinc-900 p-3 {{ $a['nova'] ? 'border-l-4 border-orange-500' : '' }}">
                <div class="text-sm font-bold">{{ $a['titulo'] }}</div>
                @if($a['corpo'])<div class="mt-0.5 text-xs text-zinc-400">{{ $a['corpo'] }}</div>@endif
                <div class="mt-1 text-[11px] text-zinc-500">{{ $a['quando'] }}</div>
            </div>
        @empty
            <p class="rounded-xl bg-zinc-900 p-4 text-sm text-zinc-400">Nenhuma notificação.</p>
        @endforelse
    </div>
@endsection
