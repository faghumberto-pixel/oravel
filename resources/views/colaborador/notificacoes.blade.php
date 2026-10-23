@extends('layouts.colaborador')
@section('titulo', 'Notificações - Oravel Colaborador')
@section('voltar', route('colab.home'))
@section('voltar_rotulo', 'Início')
@section('tela', 'Notificações')
@section('conteudo')
    <div class="mt-3 flex items-center justify-between">
        @if($avisos->isNotEmpty())
            <form method="POST" action="{{ route('colab.notificacoes.limpar') }}" onsubmit="return confirm('Apagar todas as notificações?')">
                @csrf
                <button type="submit" class="rounded-lg border border-zinc-300 px-3 py-2 text-xs font-bold text-zinc-700 active:bg-zinc-100">Limpar tudo</button>
            </form>
        @endif
    </div>

    <div class="mt-4 space-y-2">
        @forelse($avisos as $a)
            <div class="rounded-xl bg-zinc-900 p-3 {{ $a['nova'] ? 'border-l-4 border-blue-300' : '' }}">
                <div class="text-sm font-bold">{{ $a['titulo'] }}</div>
                @if($a['corpo'])<div class="mt-0.5 text-xs text-zinc-600">{{ $a['corpo'] }}</div>@endif
                <div class="mt-1 text-[11px] text-zinc-600">{{ $a['quando'] }}</div>
            </div>
        @empty
            <p class="rounded-xl bg-white p-4 text-sm text-zinc-600 border border-zinc-200 shadow-sm">Nenhuma notificação.</p>
        @endforelse
    </div>
@endsection
